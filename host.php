#!/usr/bin/php
<?php

    define('EOL', PHP_EOL);


    $config = new stdClass;
    $config->host_path = '/etc/hosts';
    $config->on_icon   = '|';
    $config->off_icon  = '-';
    $config->servername_length = 20;

    $host = new Host($config);


    class Host
    {
        protected $config
        ,         $input
        ,         $commands
        ,         $line
        ,         $servers;


        public function __construct($config)
        {
            $this->config = $config;

            $this->commands = array(
                'list'     => 'Displays a list of all hosts',
                'servers'  => 'Displays a list of all saved servers',
                'toggle'   => 'Switches a site on or off for a server',
                'addserver' => 'Adds a new server to the registry',
                'addhost'  => 'Adds a new host',
                'removeserver' => 'Removes a server from the registry',
                'removehost' => 'Removes a host'
            );

            $this->servers = array();

            $this->input = $_SERVER['argv'];
            array_shift($this->input);
            $this->input['flag'] = array();

            foreach($this->input as $k => $v) {
                if($v[0] == '-') $this->input['flag'][] = $v[1];
            }

            $this->control();
        }


        private function control()
        {
            if (empty($this->input)) {
                $this->write($this->get_help());
            }

            if (!array_key_exists($this->input[0], $this->commands)) {
                $this->write($this->get_help());
            }

            if (method_exists($this, 'cmd_'. $this->input[0])) {
                return call_user_func(array($this, 'cmd_'. $this->input[0]));
            }

            $this->write($this->get_help());
        }


        private function cmd_list()
        {
            $lines = file($this->config->host_path);

            $output  = str_pad($this->config->on_icon, 3)  . str_pad(':', 3) .' On'. EOL;
            $output .= str_pad($this->config->off_icon, 3) . str_pad(':', 3) .' Off'. EOL . EOL;
            $output .= str_pad('On/Off', 8) . str_pad('Domain', 22) . str_pad('IP/Server', $this->config->servername_length) . EOL;
            $output .= str_repeat('-', $this->config->servername_length+25) . EOL;

            foreach($lines as $line)
            {
                if (!is_numeric($line[1])) continue;

                $data = $this->strip($line) . EOL;
                list($ip, $domain) = explode(':', $data);

                if ($ip == '255.255.255.255') continue;

                $output .= '   '. str_pad($line[0] == '#' ? $this->config->off_icon : $this->config->on_icon, 5);
                $server = in_array('i', $this->input['flag']) ? $ip : $this->get_server($lines, $ip);
                $output .= str_pad(trim($domain), 22) . str_pad( $server, $this->config->servername_length ) . EOL;
            }

            $this->write($output);

        }


        private function cmd_servers($return=false)
        {
            $lines = file($this->config->host_path);
            $output = str_pad('#', 4) . str_pad('Servername', $this->config->servername_length) .'IP'. EOL;
            $output .= str_repeat('-', $this->config->servername_length) .'-------------------------' . EOL;

            $i = 0;
            foreach($lines as $line)
            {
                if ($line[0] . $line[1] . $line[2] != '###') continue;

                list($name, $ip) = explode(':', str_replace('###', '', trim($line)));
                $output .= str_pad($i+1, 4) . str_pad($name, $this->config->servername_length) . $ip. EOL;
                $this->servers[$i] = array('name' => $name, 'ip' => $ip);
                $i++;
            }

            if (!$return) $this->write($output);
            return $output;
        }


        private function cmd_toggle()
        {
            if (isset($this->input[1])) {
                $domain = $this->input[1];
            }
            else {
                echo EOL .'What host do you want to toggle?: ';
                $handle = fopen('php://stdin', 'r');
    			$domain = trim(fgets($handle));
            }

            $exists = $this->host_exists($domain);
            $data = array();

            if ($exists) {
                $lines = file($this->config->host_path);
                $line  = $lines[$this->line];

                // The host is turn off. Now turn it on!
                if ($line[0] == '#') {
                    foreach($lines as $nr => $l)
                    {
                        if ($nr == $this->line) $l = substr($l, 1);
                        $data[] = $l;
                    }
                    $switch = 'on';
                }
                else {
                    foreach($lines as $nr => $l)
                    {
                        if ($nr == $this->line) $l = '#'. $l;
                        $data[] = $l;
                    }
                    $switch = 'off';
                }

                $stringData = implode('', $data);
                file_put_contents($this->config->host_path, $stringData);

                $this->write($domain .' is now '. $switch . EOL);
            }
            else {
                // If host doesn't exists.
                $this->write('The host doesn\'t exists.');
            }

        }


        private function cmd_addhost()
        {
            echo EOL .'Please enter the domain name: ';
            $handle = fopen('php://stdin', 'r');
            $domain = trim(fgets($handle));


            if ($this->host_exists($domain)) {
                $this->write('The domain already exists.'. EOL);
            }

            echo EOL .'Here\'s a list of all your registered servers: '. EOL;
            echo $this->cmd_servers(true) . EOL;

            echo 'Now please choose a server by it\'s number or write an IP: ';
            $handle = fopen('php://stdin', 'r');
            $server = trim(fgets($handle));

            if (strpos($server, '.') === false) {
                // Server nr.
                $ip = $this->servers[$server-1]['ip'];
                echo 'You have choosen the '. $this->servers[$server-1]['name'] .' server.'.  EOL;
            }
            else {
                // IP address.
                $ip = $server;
                echo 'You have choosen the IP address '. $ip . EOL;
            }

            $data = file_get_contents($this->config->host_path);
            $data .= $ip ."\t". $domain . EOL;
            file_put_contents($this->config->host_path, $data);

            $this->write('Success. The host is now available.'. EOL);
        }


        private function cmd_removehost()
        {
            if (isset($this->input[1])) {
                $domain = $this->input[1];
            }
            else {
                echo EOL .'Please enter the domain name you wish to remove: ';
                $handle = fopen('php://stdin', 'r');
                $domain = trim(fgets($handle));
            }

            if (!$this->host_exists($domain)) {
                $this->write('The domain name doesn\'t exists.');
            }

            $lines = file($this->config->host_path);
            $data  = array();

            foreach ($lines as $nr => $line)
            {
                if ($nr == $this->line) continue;
                $data[] = $line;
            }

            file_put_contents($this->config->host_path, $data);

            $this->write('You have now removed '. $domain .' from your host file.');
        }


        private function cmd_addserver()
        {
            echo EOL . $this->cmd_servers(true) . EOL;

            echo 'Please enter the IP address you wish to add: ';
            $handle = fopen('php://stdin', 'r');
            $ip     = trim(fgets($handle));

            echo EOL .'Please enter the servername: ';
            $handle = fopen('php://stdin', 'r');
            $name   = trim(fgets($handle));

            $data = file_get_contents($this->config->host_path);
            $data .= '###'. $name .':'. $ip . EOL;
            file_put_contents($this->config->host_path, $data);

            $this->write('You have now added '. $name .' on ip '. $ip . EOL);
        }


        private function cmd_removeserver()
        {
            echo EOL . $this->cmd_servers(true) . EOL;

            echo 'Please type the number of the server you wish to remove: ';
            $handle = fopen('php://stdin', 'r');
            $server = trim(fgets($handle));

            $find = '###'. $this->servers[$server-1]['name'] .':'. $this->servers[$server-1]['ip'];
            $lines = file($this->config->host_path);
            $data = array();

            foreach ($lines as  $line)
            {
                if (trim($line) == $find) continue;
                $data[] = $line;
            }

            file_put_contents($this->config->host_path, implode('', $data));

            $this->write($this->servers[$server-1]['name'] .' was removed.'. EOL);
        }


        private function host_exists($host)
        {
            $lines = file($this->config->host_path);
            foreach($lines as $nr => $line)
            {
                if (!is_numeric($line[1])) continue;

                $data = $this->strip($line);
                list($ip, $domain) = explode(':', $data);

                if ($ip == '255.255.255.255') continue;

                // \s(google\.com)\n
                $regex = '/\s('. str_replace('.', '\\.', $host) .')\n/';
                if (preg_match($regex, $line)) {
                    $this->line = $nr;
                    return true;
                }
            }

            return false;
        }


        private function get_server($lines, $fallback)
        {
            foreach($lines as $line)
            {
                if ($line[0] . $line[1] . $line[2] != '###') continue;

                list($name, $ip) = explode(':', str_replace('###', '', trim($line)));
                if ($fallback == $ip) return $name;
            }

            return $fallback;
        }

        private function strip($var)
        {
            $var = preg_replace(array('/\n/', '/\s/', '/#/'), array('', ':', ''), $var);
            return $var;
        }


        private function get_help()
        {
            $output  = 'No such command. Showing help section.'. EOL . EOL;
            $output .= 'List of commands:'. EOL;
            $output .= '-----------------'. EOL;
            foreach($this->commands as $cmd => $desc)
            {
                $output .= str_pad($cmd, 15) . $desc . EOL;
            }

            $this->write($output);
        }


        private function write($message)
        {
            die( EOL . $message . PHP_EOL );
        }

    }
