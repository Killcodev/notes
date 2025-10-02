<?php

// src/Controller/KanbanApiController.php
namespace App\Controller;

use App\Entity\Board;
use App\Repository\ColumnRepository;
use App\Repository\CardRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class KanbanApiController extends AbstractController
{
    #[Route('/board/{id}', name: 'api_board_show', methods: ['GET'])]
    public function showBoard(
        Board $board,
        ColumnRepository $colRepo,
        CardRepository $cardRepo
    ): JsonResponse {
        $columns = $colRepo->findByBoardOrdered($board);

        $payload = [];
        foreach ($columns as $col) {
            $cards = $cardRepo->findByColumnOrdered($col);
            $payload[] = [
                'id'      => $col->getId(),
                'title'   => $col->getTitle(),
                'position'=> $col->getPosition(),
                'cards'   => array_map(fn($card) => [
                    'id'        => $card->getId(),
                    'title'     => $card->getTitle(),
                    'position'  => $card->getPosition(),
                    'desc'      => $card->getDescription(),
                ], $cards),
            ];
        }

        return $this->json([
            'board' => [
                'id'    => $board->getId(),
                'name'  => $board->getName(),
            ],
            'columns' => $payload,
        ]);
    }
}
