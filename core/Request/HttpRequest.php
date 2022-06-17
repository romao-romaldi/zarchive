<?php
/*
 * Copyright (C) 2015 Maarch
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace core\Request;
/**
 * Class for http requests
 */
class HttpRequest
    extends AbstractRequest
{

    public $headers;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->mode = 'http';

        $this->getHttpHeaders();

        $this->getContentType();       

        $this->accept = $this->getPriorisedList('HTTP_ACCEPT');

        $this->acceptLanguage = $this->getPriorisedList('HTTP_ACCEPT_LANGUAGE');

        $this->acceptEncoding = $this->getPriorisedList('HTTP_ACCEPT_ENCODING');

        $this->acceptCharset = $this->getPriorisedList('HTTP_ACCEPT_CHARSET');

        if (isset($this->headers['X-Laabs-Max-Count'])) {
            $this->maxCount = $this->headers['X-Laabs-Max-Count'];
        }

        $this->host = $_SERVER['HTTP_HOST'];

        $this->getAuthentication();

        $this->getBody();

        $this->query = urldecode($_SERVER['QUERY_STRING']);

        $this->getQueryType();

        switch (\laabs::getHttpMethod()) {
            case 'POST':
                $this->method = 'CREATE';
                //$this->arguments = array_merge($_POST, $this->arguments);
                break;

            case 'PUT':
                $this->method = 'UPDATE';
                //$this->parseUrlBody();
                break;

            case 'DELETE':
                $this->method = 'DELETE';
                //$this->parseUrlBody();
                break;

            case 'OPTIONS':
                $this->method = 'PROMPT';
                //$this->parseUrlBody();
                break;
                
            case 'GET':
            default:
                $this->method = 'READ';
                break;
        }

        $this->script = \laabs\basename($_SERVER['SCRIPT_FILENAME']);

        $this->parseUrl();
    }

    /**
     * Get a http header by name
     * @param string $name
     * 
     * @return string
     */
    public function getHeader($name)
    {
        if (isset($this->headers[$name])) {
            return $this->headers[$name];
        }
    }

    /**
     * Get the http headers
     */
    protected function getHttpHeaders()
    {
        $httpNonPrefixedHeaders = array(
            "CONTENT_TYPE",
            "CONTENT_LENGTH",
            );

        $httpHeaders = array();
        foreach ($_SERVER as $key => $value) {
            switch(true) {
                case substr($key, 0, 5) == 'HTTP_':
                    $key = substr($key, 5);
                // Continue with http header name
                case in_array($key, $httpNonPrefixedHeaders):
                    $nameParts = explode("_", $key);
                    foreach ($nameParts as $i => $namePart) {
                        $nameParts[$i] = ucfirst(strtolower($namePart));
                    }
                    $name = implode('-', $nameParts);
                    $this->headers[$name] = $value;
            }
        }
    }

    protected function getContentType()
    {
        if (!isset($_SERVER['CONTENT_TYPE'])) {
            $this->contentType = "url";

            return $this->contentType;
        } 

        $contentTypes = \laabs::getContentTypes();
        $contentType = $_SERVER['CONTENT_TYPE'];
        $mimeType = strtok($contentType, ";");
        if (isset($contentTypes[$mimeType])) {
            $this->contentType = $contentTypes[$mimeType];
        } elseif (strlen($this->body) === 0) {
            $this->contentType = null;
        } else { 
            throw new \Exception("Could not find a request content handler for the request content type '$contentType'");
        }

        return $this->contentType;
    }

    protected function getQueryType()
    {
        if (isset($_SERVER['LAABS-QUERY-TYPE'])) {
            $this->queryType = $_SERVER['LAABS-QUERY-TYPE'];
        } elseif (isset($_GET['LAABS_QUERY_TYPE'])) {
            $this->queryType = $_GET['LAABS_QUERY_TYPE'];

            $actualQuery = $_GET;
            unset($actualQuery['LAABS_QUERY_TYPE']);

            $this->query = urldecode(http_build_query($actualQuery));
        } else {
            $this->queryType = "url";
        }

        return $this->queryType;
    }

    protected function getBody()
    {
        if ($this->contentType == 'url') {
            $this->body = array_merge_recursive($_POST, $this->getFiles());
        } else {
            $this->body = fopen('php://temp', 'w+');
            $input = fopen('php://input', 'r');
            $length = stream_copy_to_stream($input, $this->body);
            rewind($this->body);

            if ($length == 0) {
                $this->body = null;
            }
        }
    }

    protected function getFiles()
    {
        $files = [];

        $keys = ['name', 'type', 'tmp_name', 'error', 'size'];

        foreach ($_FILES as $name => $item) {
            // Standard form-data files at root level
            if (is_string($item['name'])) {
                $files[$name] = $item;
                continue;
            }

            // Merged array of file values
            // root-level name =>
            //   'name' => array of subfile names
            //   'type' => array of subfile types
            //   ...
            foreach ($item['name'] as $fname => $value) {
                // 2nd level files
                if (is_string($value)) {
                    $file = [];
                    foreach ($keys as $key) {
                        $file[$key] = $item[$key][$fname];
                    }
                    $files[$name][$fname] = $file;
                    continue;
                }  

                // 3rd level : multiple files with one name
                if (is_array($value)) {
                    foreach ($value as $index => $svalue) {
                        $file = [];
                        foreach ($keys as $key) {
                            $file[$key] = $item[$key][$fname][$index];
                        }
                        $files[$name][$fname][$index] = $file;
                    } 
                }           
            }
        }

        return $files;
    }

    protected function parseUrl()
    {
        $url = $_SERVER['SCRIPT_URL'];

        // Remove frontal script
        if ($this->script) {
            $url = str_replace(LAABS_URI_SEPARATOR . $this->script, "", $url);
        }

        // Remove leading slash
        if ($url && $url[0] == LAABS_URI_SEPARATOR) {
            $url = substr($url, 1);
        }

        $this->uri = $url;
    }

    /**
     * Utility to retrieve an associative array from separated value, with priority/quality index as in RFC2616 
     * Linked list with order/priority retrieval
     *   For RFC2616 ranges with quality/priority
     *   <<< item1,item2;item3...itemN,q=0.8;...itemM,q=0.1
     *   >>> array(
     *           item1 => 1.0,
     *           item2 => 1.0,
     *           item3 => 0.8,
     *           ...
     *           itemN => 0.8,
     *           ...
     *           itemM => 0.1,
     *       )
     * @param string $key The name of the server value
     * 
     * @return array
     */
    protected function getPriorisedList($key)
    {
        $list = array();
        
        // Check in $_SERVER
        if (isset($_SERVER[$key])) {
            $priorised = array();
            foreach (\laabs\explode(LAABS_CONF_LIST_SEPARATOR, $_SERVER[$key]) as $accept) {
                $q = '1.0';
                foreach (\laabs\explode(",", $accept) as $acceptItem) {
                    if ($acceptItem[0] == 'q') {
                        $q = substr($acceptItem, 2);
                        continue;
                    }
                    $priorised[$q][] = $acceptItem;
                }
            }

            krsort($priorised);

            foreach ($priorised as $priority => $items) {
                foreach ($items as $item) {
                    $list[$item] = $priority;
                }
            }
        }

        return $list;
    }

}
