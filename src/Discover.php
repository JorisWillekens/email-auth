<?php

/**
 * Discover IMAP or/and SMTP settings
 *
 * @package		EmailAuth
 * @author		Vladimir Osipov <vladimir.g.osipov@gmail.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */

namespace EmailAuth;

class Discover
{
    public $mxServer     = null;
    public $mxServerRoot = null;
    
    /**
     * Discovers IMAP settings for an email
     *
     * @param $email
     * @return array|null
     * @throws \Exception
     */
    public function imap($email)
    {
        $prefixes = ['imap.', 'mail.', 'pop3.'];

        foreach ($prefixes as $prefix)
        {
            list ($host, $port) = $this->analyse($email, $prefix, [993, 143]);

            if ($host && $port)
            {
                return array
                (
                    'host'       => $host,
                    'port'       => $port,
                    'encryption' => 993 == $port ? 'ssl' : null,
                );
            }
        }

        return null;
    }

    /**
     * Discovers SMTP settings for an email
     *
     * @param $email
     * @return array|null
     * @throws \Exception
     */
    public function smtp($email)
    {
        $prefixes = ['smtp.', 'mail.'];

        foreach ($prefixes as $prefix)
        {
            list ($host, $port) = $this->analyse($email, $prefix, [587, 465, 25]);

            if ($host && $port)
            {
                $encTypes =
                [
                    25  => null,
                    587 => 'tls',
                    465 => 'ssl',
                ];

                return array
                (
                    'host'       => $host,
                    'port'       => $port,
                    'encryption' => @$encTypes[$port] ?: null,
                );
            }
        }

        return null;
    }

    /**
     * Discover host and port for specified prefix and ports
     *
     * @param $email
     * @param $prefix
     * @param $ports
     * @return array
     * @throws \Exception
     */
    private function analyse($email, $prefix, $ports)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        {
            throw new \Exception('Not a valid email');
        }

        $host = null;
        $domain = explode('@', $email);

        if ($mxServer = Dns::getTopMx($domain[1]))
        {
            // save MX-server information
            $mxServerDomains = explode('.', $mxServer);
            $mxServerRoot = implode('.', array_slice($mxServerDomains, -2, 2));

            $this->mxServer = $mxServer;
            $this->mxServerRoot = $mxServerRoot;
        }

        if ($port = Socket::pingPort($prefix . $domain[1], $ports))
        {
            $host = $prefix . $domain[1];
        }
        elseif ($mxServer)
        {
            if ($port = Socket::pingPort($mxServer, $ports))
            {
                $host = $mxServer;
            }
            else
            {
                $revMxServer = gethostbyaddr(gethostbyname($mxServer));
                $revMxServerDomains = explode('.', $revMxServer);
                $revMxServerRoot = @implode('.', array_slice($revMxServerDomains, -2, 2));

                if ($port = Socket::pingPort($prefix . $revMxServerRoot, $ports))
                {
                    $host = $prefix . $revMxServerRoot;
                }
                else if ($port = Socket::pingPort($prefix . $mxServerRoot, $ports))
                {
                    $host = $prefix . $mxServerRoot;
                }
            }
        }

        return [$host, $port];
    }
}
