<?php
// src/Controller/KanbanController.php
namespace App\Controller;

use App\Entity\Board;
use App\Entity\Card;
use App\Entity\Column;
use App\Repository\BoardRepository;
use App\Repository\CardRepository;
use App\Repository\ColumnRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/kanban')]
class KanbanController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ColumnRepository $colRepo,
        private CardRepository $cardRepo
    ) {}

    #[Route('/{id}', name: 'kanban_board', methods: ['GET'])]
    public function board(Board $board)
    {
        $columns = $this->colRepo->findByBoardOrdered($board);
        $cardsByCol = [];
        foreach ($columns as $c) {
            $cardsByCol[$c->getId()] = $this->cardRepo->findByColumnOrdered($c);
        }
        return $this->render('kanban/board.html.twig', compact('board','columns','cardsByCol'));
    }

    // move card to another column or change its position within the same column
    #[Route('/move-card', name: 'kanban_move_card', methods: ['POST'])]
    public function moveCard(Request $req): JsonResponse
    {
        $p = json_decode($req->getContent(), true) ?? [];
        $cardId = (int)($p['cardId'] ?? 0);
        $toColumnId = (int)($p['toColumnId'] ?? 0);
        $newIndex = (int)($p['newIndex'] ?? 0);

        /** @var Card|null $card */
        $card = $this->cardRepo->find($cardId);
        /** @var Column|null $toCol */
        $toCol = $this->colRepo->find($toColumnId);
        if (!$card || !$toCol) return new JsonResponse(['error'=>'Not found'], 404);

        $this->em->wrapInTransaction(function () use ($card, $toCol, $newIndex) {
            $fromCol = $card->getParentColumn();

            // update the list in the source column (if changing columns)
            if ($fromCol && $fromCol->getId() !== $toCol->getId()) {
                $fromCards = $this->cardRepo->findByColumnOrdered($fromCol);
                $fromCards = array_values(array_filter($fromCards, fn(Card $c)=>$c->getId() !== $card->getId()));
                foreach ($fromCards as $i=>$c) $c->setPosition($i);
                // reparent the card
                $card->setParentColumn($toCol);
            }

            // insert into the target column at newIndex
            $toCards = $this->cardRepo->findByColumnOrdered($toCol);
            // remove duplicate if moving within the same column
            $toCards = array_values(array_filter($toCards, fn(Card $c)=>$c->getId() !== $card->getId()));
            $newIndex = max(0, min($newIndex, count($toCards)));
            array_splice($toCards, $newIndex, 0, [$card]);
            foreach ($toCards as $i=>$c) $c->setPosition($i);
        });

        return new JsonResponse(['ok'=>true]);
    }

    // change the order of columns
    #[Route('/reorder-columns', name: 'kanban_reorder_columns', methods: ['POST'])]
    public function reorderColumns(Request $req): JsonResponse
    {
        $p = json_decode($req->getContent(), true) ?? [];
        $orderedIds = array_map('intval', $p['orderedColumnIds'] ?? []);
        if (!$orderedIds) return new JsonResponse(['error'=>'Bad payload'], 400);

        $this->em->wrapInTransaction(function () use ($orderedIds) {
            // fetch and arrange columns in the given order
            $cols = $this->colRepo->findBy(['id'=>$orderedIds]);
            $map = [];
            foreach ($cols as $c) $map[$c->getId()] = $c;
            foreach ($orderedIds as $i=>$id) {
                if (isset($map[$id])) $map[$id]->setPosition($i);
            }
        });

        return new JsonResponse(['ok'=>true]);
    }
}
