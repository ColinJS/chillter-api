<?php

namespace C\Command\Event;

use C\Command\AbstractCommand;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class DeleteCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setDescription('Remove an event by ID');
        $this->addArgument(
            'event_id',
            InputArgument::IS_ARRAY | InputArgument::REQUIRED,
            'Event ID (separate multiple events ids with a space)'
        );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $db = $this->getConnection();
        $app = $this->getSilexApplication();

        $eventIds = array_map("intval", $input->getArgument('event_id'));

        foreach ($eventIds as $eventId) {
            if ('1' !== $db->fetchColumn('SELECT 1 FROM `event` WHERE `id` = ?', [ $eventId ])) {
                throw new \Exception("Event (ID: $eventId) does not exist!");
            }

            $db->transactional(function () use ($app, $db, $eventId) {
                $db->delete('event_participant', [
                    'eventid' => $eventId
                ]);

                $db->delete('event_hidden', [
                    'event_id' => $eventId
                ]);

                $db->delete('event_message', [
                    'event_id' => $eventId
                ]);

                $db->delete('list', [
                    'eventid' => $eventId
                ]);

                $ids = $db->executeQuery("
                SELECT i.`id`
                FROM `expense` e
                INNER JOIN `expense_inheritor` i ON e.`id` = i.`expenseid`
                WHERE e.`eventid` = ?
            ", [ $eventId ])->fetchAll(\PDO::FETCH_COLUMN);

                $db->executeQuery(
                    'DELETE FROM `expense_inheritor` WHERE `id` IN (?)',
                    array($ids),
                    array(Connection::PARAM_INT_ARRAY)
                );

                $db->delete('expense', [
                    'eventid' => $eventId
                ]);

                $ids = $db->executeQuery("
                SELECT p.`id`
                FROM `car` c
                INNER JOIN `car_passenger` p ON c.`id` = p.`carid`
                WHERE c.`eventid` = ?
            ", [ $eventId ])->fetchAll(\PDO::FETCH_COLUMN);

                $db->executeQuery(
                    'DELETE FROM `car_passenger` WHERE `id` IN (?)',
                    array($ids),
                    array(Connection::PARAM_INT_ARRAY)
                );

                $db->delete('car', [
                    'eventid' => $eventId
                ]);

                list($logo, $banner) = $db->fetchArray("SELECT `logo`, `banner` FROM `event` WHERE`id` = ?", [ (int)$eventId ]);

                $db->delete('event', [
                    'id' => $eventId
                ]);

                $fs = new Filesystem();

                if ($logo) {
                    $fs->remove($app['root.dir'] . $app['upload.directory'] . $logo);
                }

                if ($banner) {
                    $fs->remove($app['root.dir'] . $app['upload.directory'] . $banner);
                }
            });

            $output->writeln("<info>Event (ID: $eventId) successfully removed!</info>");
        }
    }
}
