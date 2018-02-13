<?php

namespace C\WebSocket;

class WsServer extends \Ratchet\WebSocket\WsServer
{
    /**
     * Hack that allows to use "Sec-WebSocket-Protocol" as header for passing authentication token
     *
     * @param $name
     * @return bool
     */
    public function isSubProtocolSupported($name)
    {
        if (0 === strpos($name, 'Bearer')) {
            return true;
        }

        return parent::isSubProtocolSupported($name);
    }
}
