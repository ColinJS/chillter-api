<?php

namespace C\Command;

use C\Resolver\ImageResolverInterface;
use C\WebSocket;
use Ratchet\App;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class WebSocketServerCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setDescription('Run a local web socket server');
        $this->addOption(
            'hostname',
            null,
            InputOption::VALUE_OPTIONAL,
            'HTTP hostname clients intend to connect to',
            'localhost'
        );
        $this->addOption(
            'port',
            null,
            InputOption::VALUE_OPTIONAL,
            'The port to listen to',
            8080
        );
        $this->addOption(
            'address',
            null,
            InputOption::VALUE_OPTIONAL,
            'The address to listen to',
            '0.0.0.0'
        );

        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $app = new App($input->getOption('hostname'), $input->getOption('port'), $input->getOption('address'));
        $app->route('/events/{eventId}', new WebSocket\WsServer(new WebSocket\Chat(
            new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output),
            $this->getConnection(),
            $this->getSilexApplication()['dispatcher'],
            $this->getSilexApplication()[ImageResolverInterface::class]
        )), array('*'));
        $app->run();
    }
}
