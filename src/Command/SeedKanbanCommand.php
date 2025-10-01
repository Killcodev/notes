<?php

namespace App\Command;

use App\Entity\Board;
use App\Entity\Column;
use App\Entity\Card;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-kanban',
    description: 'Seeds a basic Kanban board with demo data',
)]
class SeedKanbanCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Create a demo board
        $board = new Board();
        $board->setName('Demo Board');
        $this->em->persist($board);

        // Columns + cards
        $columns = ['To Do', 'In Progress', 'Done'];
        foreach ($columns as $ci => $title) {
            $column = new Column();
            $column->setTitle($title);
            $column->setPosition($ci);
            $column->setBoard($board);
            $this->em->persist($column);

            // Cards in the column
            for ($i = 0; $i < 3; $i++) {
                $card = new Card();
                $card->setTitle($title.' Task '.($i+1));
                $card->setDescription('Sample description for card '.$i);
                $card->setPosition($i);
                // Use property names as in your Card entity
                $card->setParentColumn($column); // or setColumnRef() if you named the field differently
                $this->em->persist($card);
            }
        }

        $this->em->flush();

        $output->writeln('<info>Demo board has been seeded!</info>');
        return Command::SUCCESS;
    }
}