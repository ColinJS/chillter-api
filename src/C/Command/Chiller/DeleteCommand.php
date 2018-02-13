<?php

namespace C\Command\Chiller;

use C\Command\AbstractCommand;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class DeleteCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setDescription('Remove a chiller by ID');
        $this->addArgument(
            'chiller_id',
            InputArgument::IS_ARRAY | InputArgument::REQUIRED,
            'Chiller ID (separate multiple chiller ids with a space)'
        );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $db = $this->getConnection();
        $fs = new Filesystem();
        $app = $this->getSilexApplication();

        $ids = array_map("intval", $input->getArgument('chiller_id'));

        foreach ($ids as $id) {
            if ('1' !== $db->fetchColumn("SELECT 1 FROM `chiller` WHERE `id` = ?", [ $id ])) {
                throw new \Exception("Chiller (ID: $id) does not exist!");
            }

            $filesToRemove = [];

            $db->transactional(function () use ($db, $output, $id, &$filesToRemove) {
                $events = $db->executeQuery("SELECT `id` FROM `event` WHERE `chillerid` = ?", [ $id ])
                    ->fetchAll(\PDO::FETCH_COLUMN);

                $this->executeEventRemoval($events, $output);

                $parameters = [
                    'chillerId' => $id
                ];

                $carIds = $db->executeQuery("SELECT `id` FROM `car` WHERE `chillerid` = :chillerId", $parameters)
                    ->fetchAll(\PDO::FETCH_COLUMN);

                $db->executeUpdate(
                    "DELETE FROM `car_passenger` WHERE `carid` IN (?);",
                    [ $carIds ],
                    [ Connection::PARAM_INT_ARRAY ]
                );

                $images = $db->executeQuery("SELECT `url` FROM `chiller_photo` WHERE `userid` = ?", [ $id ])
                    ->fetchAll(\PDO::FETCH_COLUMN);

                $filesToRemove = array_merge($filesToRemove, $images);

                $customChills = $db->executeQuery("SELECT `id` FROM `chills_custom` WHERE `chiller_id` = ?", [ $id ])
                    ->fetchAll(\PDO::FETCH_COLUMN);

                foreach ($customChills as $customChillId) {
                    $db->delete('chills_custom_element', [
                        'chills_custom_id' => $customChillId
                    ]);

                    $db->delete('chills_custom_expense', [
                        'chills_custom_id' => $customChillId
                    ]);

                    $db->delete('chills_custom_participant', [
                        'chills_custom_id' => $customChillId
                    ]);
                }

                $query = <<<SQL
                    DELETE FROM `event_message` WHERE `event_id` = :chillerId;
                    DELETE FROM `blacklist` WHERE `blocker` = :chillerId OR `blockee` = :chillerId;
                    DELETE FROM `chills_custom` WHERE `chiller_id` = :chillerId;
                    DELETE FROM `chiller_friends` WHERE `first_id` = :chillerId OR `second_id` = :chillerId;
                    DELETE FROM `chiller_home` WHERE `chiller_id` = :chillerId;
                    DELETE FROM `expense_inheritor` WHERE `chillerid` = :chillerId;
                    DELETE FROM `expense` WHERE `chillerid` = :chillerId;
                    DELETE FROM `event_hidden` WHERE `chiller_id` = :chillerId;
                    DELETE FROM `list` WHERE `created_by` = :chillerId;
                    UPDATE `list` SET `assigned_to` = NULL WHERE `assigned_to` = :chillerId;
                    DELETE FROM `car_passenger` WHERE `chillerid` = :chillerId;
                    DELETE FROM `car` WHERE `chillerid` = :chillerId;
                    DELETE FROM `event_participant` WHERE `chillerid` = :chillerId;
                    DELETE FROM `chiller_photo` WHERE `userid` = :chillerId;
                    DELETE FROM `chiller` WHERE `id` = :chillerId;
SQL;

                $db->executeQuery($query, $parameters);

                $output->writeln("<info>User (ID: $id) successfully removed!</info>");
            });


            foreach ($filesToRemove as $file) {
                $fs->remove($app['root.dir'] . $app['upload.directory'] . $file);
            }
        }
    }

    protected function executeEventRemoval($eventId, OutputInterface $output)
    {
        $command = $this->getApplication()->find('chillter:event:delete');

        $input = new ArrayInput([
            'command' => 'chillter:event:delete',
            'event_id' => $eventId,
        ]);

        $command->run($input, $output);
    }
}
