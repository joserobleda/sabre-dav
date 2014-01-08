<?php

namespace Sabre\DAV;

use
    Psr\LoggerInterface,
    Psr\LogLevel;

class DebugPlugin extends ServerPlugin {

    protected $logger;
    protected $startTime;

    protected $contentTypeWhiteList = array(
        '|^text/|',
        '|^application/xml|',
    );

    public function __construct(LoggerInterface $logger) {

        $this->logger = $logger;
        $this->startTime = time();

    }

    public function getPluginName() {

        return 'debuglogger';

    }

    /**
     * Initializes the plugin
     *
     * @param Server $server
     * @return void
     */
    public function initialize(Server $server) {

        $this->server = $server;
        $server->on('beforeMethod', [$this, 'beforeMethod'], 5);
        $this->log(LogLevel::INFO, 'Initialized plugin. Request time ' . $this->startTime . ' (' . date(DateTime::RFC2822,$this->startTime) . '). Version: ' . Version::VERSION);

    }

    /**
     * Very first event to be triggered. This allows us to log the HTTP
     * request.
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return void
     */
    public function beforeMethod(RequestInterface $request, ResponseInterface $response) {

        $this->log(LogLevel::INFO, $request->getMethod() . ' ' . $request->getPath());

        $this->log(LogLevel::DEBUG, 'Plugins loaded:');
        foreach($this->server->getPlugins() as $pluginName => $plugin) {
            $this->log(LogLevel::DEBUG,'  ' . $pluginName . ' (' . get_class($plugin) . ')');
        }
        $this->log(LogLevel::DEBUG, 'SabreDAV server Base URI: ' . $this->server->getBaseUri());
        $this->log(LogLevel::DEBUG,'Headers:');
        foreach($this->server->httpRequest->getHeaders() as $key=>$value) {
            $this->log(LogLevel::DEBUG,'  '  . $key . ': ' . $value);
        }

        // We're only going to show the request body if it's text-based. The
        // maximum size will be 10k.
        $contentType = $request->getHeader('Content-Type');
        $showBody = false;
        foreach($this->contentTypeWhiteList as $wl) {

            if (preg_match($wl, $contentType)) {
                $showBody = true;
                break;
            }

        }
        if ($showBody) {
            // We need to grab the body, and put it in an intermediate stream.
            $newBody = fopen('php://temp','r+');
            $body = $request->getBodyAsStream();

            // Only grabbing the first 10kb
            $strBody = fread($body, 10240);

            $this->log(LogLevel::DEBUG, 'Request body:');
            $this->log(LogLevel::DEBUG, $strBody);

            // Writing the bytes we already read
            fwrite($newBody, $strBody);

            // Writing the remainder of the input body, if there's anything
            // left.
            stream_copy_to_stream($body, $newBody);
            rewind($newBody);

            $request->setBody($newBody, true);

        }

    }

    /**
     * Appends a message to the log
     *
     * @param string $message
     * @return void
     */
    public function log($logLevel, $message) {

        $this->logger->log($logLevel, $message);

    }

}