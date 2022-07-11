<?php
/**
 * Class file for Laabs Dynamic Kernel
 * @package core\Kernel
 */
namespace core\Kernel;

/**
 * Class Laabs Dynamic Kernel
 *
 * @extends core\Kernel\AbstractKernel
 */
class PresentationKernel
    extends AbstractKernel
{
    /* Constants */

    /* Properties */
    /**
     * The Command definition if found in presentation commands
     * @var \core\Reflection\UserCommand
     */
    public $userCommand;

    /**
     * The userInput Router
     * @var \core\Route\UserInputRouter
     */
    public $userInputRouter;

    /**
     * The View Router
     * @var \core\Route\ViewRouter
     */
    public $viewRouter;

    /**
     * The message parts
     * @var array
     */
    public $userMessage = array();

    /**
     * The Return of the business action(s)
     * @var array
     */
    public $serviceReturns = array();

    /* Methods */
    /**
     * Run the kernel to process request
     */
    public static function run()
    {
        /* Initalize components app/dependecy/bundle */
        self::$instance->attachObservers();

        try {
            \core\Observer\Dispatcher::notify(LAABS_REQUEST, self::$instance->request);

            /* Establish routes (input, action, output) */
            self::$instance->setRoutes();
        
            self::$instance->parseRequest();

            /* Call Command */
            self::$instance->callUserCommand();
        } catch (\Exception $exception) {
            if (!self::$instance->handleException($exception)) {
                self::$instance->response->setBody((string) $exception);
                self::$instance->response->setCode(500);
                self::$instance->sendResponse();

                return;
            }
        }

        self::$instance->presentResponse();

        \core\Observer\Dispatcher::notify(LAABS_RESPONSE, self::$instance->response);

        //Send response;
        self::$instance->sendResponse();
    }

    /**
     * Set the action router for kernel
     * @access protected
     */
    protected function setRoutes()
    {
        $this->userCommand = \laabs::command($this->request->method, $this->request->uri);

        // Notify of command
        \core\Observer\Dispatcher::notify(LAABS_USER_COMMAND, $this->userCommand);

        if ($this->response->mode == 'http') {
            $this->response->setHeader("X-Laabs-UserStory", $this->userCommand->userStory);
        }

        $this->setUserInputRouter();
        $this->setViewRouter();
    }

    protected function setUserInputRouter()
    {
        if (isset($this->userCommand->userInput)) {
            $this->userInputRouter = new \core\Route\UserInputRouter($this->userCommand->userInput);
        } else {
            try {
                $this->userInputRouter = new \core\Route\UserInputRouter($this->userCommand->getName());
            } catch (\Exception $e) {
            }
        }
    }

    protected function setViewRouter()
    {
        if (isset($this->userCommand->view)) {
            $this->viewRouter = new \core\Route\ViewRouter($this->userCommand->view);
        } else {
            try {
                $this->viewRouter = new \core\Route\ViewRouter($this->userCommand->getName());
            } catch (\Exception $e) {
            }
        }
    }

    /**
     * Parse current request body with parser
     * @access protected
     */
    protected function parseRequest()
    {
        if (isset($this->userInputRouter)) {
            if ($this->response->mode == 'http') {
                $this->response->setHeader("X-Laabs-Composer", $this->userInputRouter->uri);
            }

            $composer = $this->userInputRouter->composer->newInstance();
            $contents = stream_get_contents($this->request->body);
            $this->userMessage = $this->userInputRouter->userInput->compose($composer, $contents, $this->request->query);
        } else {
            switch ($this->request->queryType) {
                case 'lql':
                    // Parse query string
                    //$this->userMessages = \laabs::parseQueryString($this->request->query);

                case 'url':
                default:
                    $this->userMessage = $_GET;
                    break;
            }
            if (!is_null($this->request->body)) {
                switch ($this->request->contentType) {
                    case 'url':
                        $bodyArguments = $this->request->body;
                        break;

                    case 'json':
                        $bodyArguments = (array) \core\Encoding\json::decodeStream($this->request->body);
                        break;

                    default:
                        $bodyArguments = [$this->request->body];
                }

                $this->userMessage = array_merge($this->userMessage, $bodyArguments);
            }
        }
    }

    /**
     * Call current command with userStory
     * @access protected
     */
    protected function callUserCommand()
    {
        if (empty($this->userCommand->services)) {
            return;
        }

        foreach ($this->userCommand->services as $name => $service) {
            $this->serviceReturns[] = $this->callService($service);
        }

        // Notify of command for authorizations and log
        \core\Observer\Dispatcher::notify(LAABS_COMMAND_RETURN, $this->serviceReturns);
    }

    protected function callService($service)
    {
        $pathRouter = new \core\Route\PathRouter($service);
        $servicePath = $pathRouter->path;

        // Get service message from request arguments and parsed body
        try {
            // cast message
            $serviceMessage = $servicePath->getMessage($this->userMessage);
        } catch (\core\Exception $e) {
            $badRequestException = new \core\Exception\BadRequestException();

            $badRequestException->errors[] = new \core\Error($e->getFormat(), $e->getVariables());

            throw $badRequestException;
        } catch (\Exception $e) {
            throw new \core\Exception\BadRequestException();
        }
        
        $valid = \laabs::validateMessage($serviceMessage, $servicePath);
        
        if (!$valid) {
            $e = new \core\Exception\BadRequestException();
            $e->errors = \laabs::getValidationErrors();

            throw $e;
        }

        // Extract service path variables from received user command AND previous services named returns
        if (!empty($servicePath->variables)) {
            foreach ($servicePath->variables as $name => $value) {
                switch (true) {
                    // Value available from command
                    case isset($this->userCommand->variables[$name]):
                        $value = $this->userCommand->variables[$name];
                        break;
                    // Value available in a previous service return
                    case isset($this->serviceReturns[$name]):
                        $value = $this->serviceReturns[$name];
                        break;
                }

                $servicePath->setVariable($name, $value);
            }
        }

        \core\Observer\Dispatcher::notify(LAABS_SERVICE_PATH, $servicePath, $serviceMessage);

        // Get controller action
        if (isset($servicePath->action)) {
            $actionRouter = new \core\Route\ActionRouter($servicePath->action);
        } else {
            $actionRouter = new \core\Route\ActionRouter($servicePath->getName());
        }

        // Order arguments using action parameters
        $parameters = $actionRouter->action->getParameters();
        
        $actionParameters = array();
        foreach ($parameters as $parameter) {
            switch (true) {
                // Value available in route pattern
                case isset($servicePath->variables[$parameter->name]):
                    $value = $servicePath->variables[$parameter->name];
                    break;

                // Value available from request arguments or body: cast into message part type
                case isset($serviceMessage[$parameter->name]):
                    $value = $serviceMessage[$parameter->name];
                    break;

                // Default value
                case $parameter->isDefaultValueAvailable():
                    $value = $parameter->getDefaultValue();
                    break;

                // Optional : null
                case $parameter->isOptional():
                    $value = null;
                    break;

                // No other case should raise an exception
                default:
                    // Throw exception
                    $value = null;
            }

            $actionParameters[$parameter->name] = $value;
        }

        // Backward remove null values from array of arguments
        do {
            $arg = end($actionParameters);
            if ($arg === null) {
                array_pop($actionParameters);
            }
        } while ($arg === null && count($actionParameters));

        $controller = $actionRouter->controller->newInstance();

        $serviceReturn = $actionRouter->action->call($controller, $actionParameters);

        \core\Observer\Dispatcher::notify(LAABS_SERVICE_RETURN, $serviceReturn);

        return $serviceReturn;
    }

    /**
     * Handle Exception sent by action
     * @param \Exception $exception The Exception thrown by the Action
     *
     * @return bool
     */
    protected function handleException(\Exception $exception)
    {

        \core\Observer\Dispatcher::notify(LAABS_BUSINESS_EXCEPTION, $exception);

        // Manage specific exception handler
        $exceptionClass = get_class($exception);
        $exceptionName = \laabs\basename($exceptionClass);

        if ($this->response->mode == 'http') {
            $this->response->setHeader("X-Laabs-Exception", $exceptionClass."; ".str_replace("\n", " ", $exception->getMessage())." in ".$exception->getFile().":".$exception->getLine());
        }

        $this->serviceReturns = array($exception);

        if ($exception instanceof \core\Exception) {
            $this->response->setCode($exception->getCode());
        } else {
            $this->response->setCode(500);
        }

        // Try to find view for the raised exception else send exception as string as response content
        switch (true) {
            case isset($this->viewRouter) && $this->viewRouter->presenter->hasView($exceptionName) :
                $this->viewRouter->setView($exceptionName);

                return true;

            case isset($this->viewRouter) && $this->viewRouter->presenter->hasView('Exception'):
                $this->viewRouter->setView('Exception');

                return true;

            case \laabs::presentation()->hasPresenter('Exception'):
                $reflectionRouter = new \ReflectionClass('core\Route\ViewRouter');
                $this->viewRouter = $reflectionRouter->newInstanceWithoutConstructor();

                $this->viewRouter->presentation = \laabs::presentation();

                $presenter = \laabs::presentation()->getPresenter('Exception');
                $this->viewRouter->setPresenter("Exception");
                switch (true) {
                    case $this->viewRouter->presenter->hasView($exceptionName) :
                        $this->viewRouter->setView($exceptionName);

                        return true;

                    case $this->viewRouter->presenter->hasView('Exception'):
                        $this->viewRouter->setView('Exception');

                        return true;
                }
        }

        return false;
    }

    /**
     * Present current return to set response body with presenter
     * @access protected
     */
    protected function presentResponse()
    {
        if (isset($this->viewRouter)) {
            if ($this->response->mode == 'http') {
                $this->response->setHeader("X-Laabs-View", $this->viewRouter->uri);
            }

            $presenter = $this->viewRouter->presenter->newInstance();
            
            $presenterArgs = $this->serviceReturns;

            if (isset($this->userCommand->variables)) {
                $presenterArgs = array_merge($presenterArgs, $this->userCommand->variables); 
            }
            $presenterArgs = array_merge($presenterArgs, $this->userMessage);
            
            $content = $this->viewRouter->view->present($presenter, array_values($presenterArgs));

        } else {

            switch ($this->response->contentType) {
                case 'json':
                default:
                    if (count($this->serviceReturns) == 1) {
                        $content = \core\Encoding\json::encode(reset($this->serviceReturns));
                    } else {
                        $content = \core\Encoding\json::encode($this->serviceReturns);
                    }

                    break;
            }
        }

        $this->response->setBody($content);

        if (empty($this->response->getContentType())) {
            $this->guessResponseType();
        }

        if (empty($this->response->getHeader('Content-Language'))) {
            $this->guessResponseLanguage();
        }

        if (is_scalar($this->response->body)) {
            $this->response->setHeader('Content-Length', strlen($this->response->body));
        }
    }

        /**
     * Guess the response content type from body or the request "Accept"
     * @return string The ContentType code or "text" as a default value
     */
    protected function guessResponseType()
    {
        
        $finfo = new \finfo();
        
        if (is_scalar($this->response->body)) {
            $mimeType = $finfo->buffer($this->response->body, FILEINFO_MIME_TYPE);
            $encoding = $finfo->buffer($this->response->body, FILEINFO_MIME_ENCODING);
        } elseif (is_resource($this->response->body)) {
            $metadata = stream_get_meta_data($this->response->body);
            if ($metadata['wrapper_type'] == 'plainfile') {
                $mimeType = $finfo->file($metadata['uri'], FILEINFO_MIME_TYPE);
                $encoding = $finfo->file($metadata['uri'], FILEINFO_MIME_ENCODING);
            } else {
                return;
            }
        }

        $contentType = $mimeType;

        if ($mimeType == "text/plain") {
            $laabsContentTypes = \laabs::getContentTypes();
            $laabsContentType = 'text';
            foreach ($this->request->accept as $acceptedMimeType => $priority) {
                if (isset($laabsContentTypes[$acceptedMimeType])) {
                    $laabsContentType = $laabsContentTypes[$acceptedMimeType];

                    break;
                }
            }

            switch (strtolower($laabsContentType)) {
                case 'css':
                case 'less':
                    $contentType = "text/css";
                    break;

                case 'js':
                case 'json':
                    $contentType = "application/javascript";
                    break;

                case 'csv':
                    $contentType = "text/csv";
                    break;

                case 'html':
                    $contentType = "text/html";
                    break;
            }
        }

        $this->response->setContentType($contentType);
    }

     /**
     * Guess the response content language from the request "AcceptLanguage"
     * @return string The ContentLanguage code or "en" as a default value
     */
    protected function guessResponseLanguage()
    {
        $contentLanguages = \laabs::getContentLanguages();
        $requestAcceptLangs = $this->request->acceptLanguage;
        $contentLanguage = 'en';

        if (!empty($requestAcceptLangs)) {
            foreach ($requestAcceptLangs as $locale => $proprity) {
                if (isset($contentLanguages[$locale])) {
                    $contentLanguage = $contentLanguages[$locale];

                    break;
                }
            }
        }

        $this->response->setHeader('Content-Language', $contentLanguage);
    }


}
