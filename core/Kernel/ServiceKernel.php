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
class ServiceKernel extends AbstractKernel
{
    /* Constants */

    /* Properties */
    /**
     * The service path definition
     * @var \core\Reflection\Path
     */
    public $servicePath;

    /**
     * The Action router
     * @var \core\Route\ActionRouter
     */
    public $actionRouter;

    /**
     * The Input Router
     * @var \core\Route\InputRouter
     */
    public $inputRouter;

    /**
     * The Output Router
     * @var \core\Route\OutputRouter
     */
    public $outputRouter;

    /**
     * The message parts
     * @var array
     */
    public $serviceRequest = array();

    /**
     * The arguments to pass to action during call
     * @var array
     */
    public $actionArguments = array();

    /**
     * The Return of the action
     * @var mixed
     */
    public $actionReturn;

    /**
     * The service response
     * @var mixed
     */
    public $serviceReturn;


    /* Methods */
    /**
     * Run the kernel to process request
     */
    public static function run()
    {
        /* Initalize components app/dependecy/bundle */
        self::$instance->attachObservers();

        \core\Observer\Dispatcher::notify(LAABS_REQUEST, self::$instance->request);

        try {
            /* Establish routes (input, action, output) */
            self::$instance->setRoutes();

            // Parse request to get arguments from request content
            self::$instance->parseRequest();

            // Validate request
            self::$instance->validateRequest();

            // Extract action arguments
            self::$instance->getActionArguments();

            /* Call Action */
            self::$instance->callAction();
        } catch (\Exception $exception) {
            $handled = self::$instance->handleException($exception);
            
            if (!$handled) {
                self::$instance->response->setBody((string) $exception);
                self::$instance->response->setCode($exception->getCode());

                self::$instance->sendResponse();

                return;
            }
        }

        // Serialize response or exception
        self::$instance->serializeOutput();

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
        $this->servicePath = \laabs::route($this->request->method, $this->request->uri);

        \core\Observer\Dispatcher::notify(LAABS_SERVICE_PATH, $this->servicePath);

        // If a service route is defined, route to input, action and output if presentation layer requires it
        if ($this->response->mode == 'http') {
            $this->response->setHeader("X-Laabs-Service", $this->servicePath->getName());
        }

        $this->setInputRouter();
        $this->setActionRouter();
        $this->setOutputRouter();

    }

    protected function setActionRouter()
    {
        if (isset($this->servicePath->action)) {
            $this->actionRouter = new \core\Route\ActionRouter($this->servicePath->action);
        } else {
            $this->actionRouter = new \core\Route\ActionRouter($this->servicePath->getName());
        }

        \core\Observer\Dispatcher::notify(LAABS_ACTION, $this->actionRouter->action);
    }

    protected function setInputRouter()
    {
        try {
            $this->inputRouter = new \core\Route\InputRouter($this->servicePath->getName(), $this->request->contentType);

            \core\Observer\Dispatcher::notify(LAABS_INPUT, $this->inputRouter->input, $this->request);
        } catch (\Exception $e) {
        }
    }

    protected function setOutputRouter()
    {
        try {
            $this->outputRouter = new \core\Route\OutputRouter($this->servicePath->getName(), $this->response->contentType);

            \core\Observer\Dispatcher::notify(LAABS_OUTPUT, $this->outputRouter->output, $this->response);
        } catch (\Exception $e) {
        }
    }

    /**
     * Parse current request body with parser
     * @access protected
     */
    protected function parseRequest()
    {
        switch ($this->request->queryType) {
            case 'arg':
                $this->userMessage = (array) $this->request->query;
                break;

            case 'lql':
                // Parse query string

            case 'url':
            default:
                $this->userMessage = $_GET;
                break;
        }

        $bodyArguments = array();

        if (!is_null($this->request->body)) {   
            if (isset($this->inputRouter)) {
                if ($this->response->mode == 'http') {
                    $this->response->setHeader("X-Laabs-Parser", $this->inputRouter->uri . "; type=" . $this->request->contentType);
                }
                $parser = $this->inputRouter->parser->newInstance();
                $bodyArguments = $this->inputRouter->input->parse($parser, $this->request->body);
            } else {
                switch ($this->request->contentType) {
                    case 'php':
                        $bodyArguments = stream_get_contents($this->request->body);
                        break;

                    case 'url':
                        $bodyArguments = $this->request->body;
                        break;

                    case 'json':
                        $bodyArguments = (array) \core\Encoding\json::decodeStream($this->request->body);
                        break;

                    default:
                        $bodyArguments = [$this->request->body];
                }
            }
            
            $this->userMessage = array_merge($this->userMessage, $bodyArguments);
        }
    }

    /**
     * Validate request
     */
    protected function validateRequest()
    {
        $this->serviceRequest = $this->servicePath->getMessage($this->userMessage);
        
        $valid = \laabs::validateMessage($this->serviceRequest, $this->servicePath);
        
        if (!$valid) {
            $e = new \core\Exception\BadRequestException();
            $e->errors = \laabs::getValidationErrors();

            throw $e;
        }
    }

    /**
     * Get action arguments from message
     * @access protected
     */
    protected function getActionArguments()
    {
        // Get action parameters
        $parameters = $this->actionRouter->action->getParameters();

        foreach ($parameters as $parameter) {
            switch (true) {
                // Value available in route pattern
                case isset($this->servicePath->variables[$parameter->name]):
                    $value = $this->servicePath->variables[$parameter->name];
                    break;

                // Value available from message parts: cast to model object
                case isset($this->serviceRequest[$parameter->name]):
                    $value = $this->serviceRequest[$parameter->name];
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

            $this->actionArguments[$parameter->name] = $value;
        }

        // Backward remove null values from array
        do {
            $arg = end($this->actionArguments);
            if ($arg === null) {
                array_pop($this->actionArguments);
            }
        } while ($arg === null && count($this->actionArguments));
    }

    /**
     * Call current action with controller and set "return" property
     * @access protected
     */
    protected function callAction()
    {
        // Notify of service for authorizations and log
        //\core\Observer\Dispatcher::notify(LAABS_SERVICE_PATH, $this->servicePath, $this->actionArguments);

        if ($this->response->mode == 'http') {
            $this->response->setHeader("X-Laabs-Controller", $this->actionRouter->uri);
        }

        $controller = $this->actionRouter->controller->newInstance();
        $this->actionReturn = $this->actionRouter->action->call($controller, array_values($this->actionArguments));

        //$serviceReturnType = $this->serviceRoute->getReturnType();
        //$this->serviceReturn = \laabs::castMessage($this->actionReturn, $serviceReturnType);
        $this->serviceReturn = $this->actionReturn;

        \core\Observer\Dispatcher::notify(LAABS_SERVICE_RETURN, $serviceReturn);
    }

    /**
     * Handle Exception sent by action
     * @param \Exception $exception The Exception thrown by the Action
     *
     * @return bool
     */
    public function handleException(\Exception $exception)
    {

        \core\Observer\Dispatcher::notify(LAABS_BUSINESS_EXCEPTION, $exception);

        // Manage specific exception handler
        $exceptionClass = get_class($exception);
        $exceptionName = \laabs\basename($exceptionClass);

        if ($this->response->mode == 'http') {
            $this->response->setHeader("X-Laabs-Exception", $exceptionClass . "; " . str_replace("\n", " ", $exception->getMessage()));

            $this->response->setCode($exception->getCode());
        }
        // Try to find serializer output for the raised exception else send exception as string as response content
        if (isset($this->outputRouter)) {
            switch (true) {
                case $this->outputRouter->serializer->hasOutput($exceptionName):
                    $this->outputRouter->setOutput($exceptionName);
                    break;

                case $this->outputRouter->serializer->hasOutput('Exception'):
                    $this->outputRouter->setOutput('Exception');
                    break;
            }
        }
        
        $this->serviceReturn = $exception;

        return true;
    }

    /**
     * Serialize current return to set response body with serializer
     * @access protected
     */
    protected function serializeOutput()
    {
        if (isset($this->outputRouter)) {
            if ($this->response->mode == 'http') {
                $this->response->setHeader("X-Laabs-Serializer", $this->outputRouter->uri . "; type=" . $this->response->contentType);
            }

            $serializer = $this->outputRouter->serializer->newInstance();

            $content = $this->outputRouter->output->serialize($serializer, $this->serviceReturn);
        } elseif (is_resource($this->serviceReturn)) {
            $content = $this->serviceReturn;
        } else {
            if (is_null($this->serviceReturn)) {
                return;
            }

            $mimetype = $this->guessResponseType();

            $types = \laabs::getContentTypes();
            $type = null;
            if (isset($types[$mimetype])) {
                $type = $types[$mimetype];
            }
            switch ($type) {
                case 'json':
                case 'html':
                    $content = \core\Encoding\json::encode($this->serviceReturn);
                    break;
                case 'text':
                default:
                    $content = \core\Encoding\text::encode($this->serviceReturn);
            }

            if ($this->guessRequestMode() == 'http') {
                $this->response->setContentType($mimetype);
            }
        }

        $this->response->setBody($content);
    }

    /**
     * Guess the response content type from the request "Accept"
     * @return string The ContentType code or "text" as a default value
     */
    protected function guessResponseType()
    {
        $contentTypes = \laabs::getContentTypes();
        $requestAccepts = $this->request->accept;

        foreach ($requestAccepts as $mimetype => $priority) {
            if (isset($contentTypes[$mimetype])) {
                return $mimetype;
            }
        }
    }

    /**
     * Guess the response content type from body or the request "Accept"
     * @return string The ContentType code or "text" as a default value
     */
    protected function setResponseType()
    {
        
        $finfo = new \finfo();
        $mimeType = $finfo->buffer($this->response->body, FILEINFO_MIME_TYPE);
        $encoding = $finfo->buffer($this->response->body, FILEINFO_MIME_ENCODING);

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

        if ($this->guessRequestMode() == 'http') {
            $this->response->setContentType($contentType);
        }
    }
}
