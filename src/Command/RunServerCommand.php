<?php

declare(strict_types=1);

namespace Amarkhay\Traceroute\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'traceroute:run-server')]
class RunServerCommand extends Command
{
    protected function configure()
    {
        $this
            ->addArgument('host', InputArgument::REQUIRED, 'Destination host')
            ->addOption('max-hops', 'm', InputOption::VALUE_OPTIONAL, 'Max hops', 256)
        ;
    }

    /**
     * Articles:
     * @link https://www.slashroot.in/how-does-traceroute-work-and-examples-using-traceroute-command
     * @link https://alexanderell.is/posts/toy-traceroute/
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (PHP_OS === 'Darwin') {
            define("IP_TTL", 4);
        } else {
            define("IP_TTL", 2);
        }

        $destinationAddress = $input->getArgument('host');
        $maxHops = (int) $input->getOption('max-hops');
        $destinationPort = random_int(33434, 33534);
        $ttl = 1;

        $io = new SymfonyStyle($input, $output);

        $socket = socket_create(AF_INET, SOCK_RAW, getprotobyname('icmp'));
        if ($socket === false) {
            $io->error('socket_create() failed: reason: ' . socket_strerror(socket_last_error()));
            return Command::FAILURE;
        }

        socket_set_option($socket, IPPROTO_IP, IP_TTL, $ttl);
        socket_set_option($socket, SOL_SOCKET, SO_RCVBUF, 2048);

        $destinationIP = gethostbyname($destinationAddress);
        /* ICMP пакет с рассчитанной контрольной суммой */
        $packet = "\x08\x00\x7d\x4b\x00\x00\x00\x00PingHost";
        $packetBytes = strlen($packet);

        $io->writeln("Traceroute to $destinationAddress ($destinationIP), maximum hops: $maxHops, $packetBytes bytes packets");

        while ($ttl <= $maxHops) {
            $startTime = microtime(true);

            // Отправляем пакет
            socket_sendto($socket, $packet, $packetBytes, 0, $destinationIP, $destinationPort);
            // Ожидаем ответ
            $bytesReceived = socket_recvfrom($socket, $response, 1024, 0, $from, $port);
            $hostFrom = gethostbyaddr($from);
            $endTime = microtime(true);
            $elapsedTime = round(($endTime - $startTime) * 1000); // Время в миллисекундах

            if ($bytesReceived > 0) {
                $io->writeln("$ttl\t$hostFrom\t($from)\t$elapsedTime ms");
            } else {
                $io->writeln("$ttl\t*\tRequest timed out");
            }

            if ($from === $destinationIP) {
                return Command::SUCCESS;
            }

            $ttl++;
            socket_set_option($socket, IPPROTO_IP, IP_TTL, $ttl);
            usleep(500);  // Пауза 500 мс перед отправкой следующего пакета
        }

        socket_close($socket);

        return Command::SUCCESS;
    }
}