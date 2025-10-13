<?php
// src/Controller/KanbanController.php
namespace App\Controller;

use App\Entity\Board;
use App\Entity\Card;
use App\Entity\Column;
use App\Repository\CardRepository;
use App\Repository\ColumnRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

    #[Route('/{id}/rename-board', name: 'kanban_board_rename', methods: ['POST'])]
    public function renameBoard(Board $board, Request $request): Response
    {
        $token = (string)$request->request->get('_token');
        if (!$this->isCsrfTokenValid('board_rename'.$board->getId(), $token)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $request->isXmlHttpRequest()
                ? new JsonResponse(['error' => 'Invalid CSRF token.'], 403)
                : $this->redirectToRoute('kanban_board', ['id' => $board->getId()]);
        }

        $name = trim((string)$request->request->get('name'));
        if ($name === '') {
            $this->addFlash('error', 'Provide a board name.');
            return $request->isXmlHttpRequest()
                ? new JsonResponse(['error' => 'Provide a board name.'], 422)
                : $this->redirectToRoute('kanban_board', ['id' => $board->getId()]);
        }

        $board->setName($name);
        $this->em->flush();
        $this->addFlash('success', 'Board updated.');

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['ok' => true, 'name' => $board->getName()]);
        }

        return $this->redirectToRoute('kanban_board', ['id' => $board->getId()]);
    }

    #[Route('/{id}/delete-board', name: 'kanban_board_delete', methods: ['POST'])]
    public function deleteBoard(Board $board, Request $request): Response
    {
        $token = (string)$request->request->get('_token');
        if (!$this->isCsrfTokenValid('board_delete'.$board->getId(), $token)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('kanban_board', ['id' => $board->getId()]);
        }

        $this->em->wrapInTransaction(function () use ($board) {
            $this->em->remove($board);
            $this->em->flush();
        });

        $this->addFlash('success', 'Board deleted.');

        return $this->redirectToRoute('base_index');
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

    #[Route('/{id}/columns', name: 'kanban_add_column', methods: ['POST'])]
    public function addColumn(Board $board, Request $request): Response
    {
        $token = (string)$request->request->get('_token');
        if (!$this->isCsrfTokenValid('column_add'.$board->getId(), $token)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('kanban_board', ['id' => $board->getId()]);
        }

        $title = trim((string)$request->request->get('title'));
        if ($title === '') {
            $this->addFlash('error', 'Provide a column name.');
            return $this->redirectToRoute('kanban_board', ['id' => $board->getId()]);
        }

        $column = (new Column())
            ->setBoard($board)
            ->setTitle($title)
            ->setPosition($this->colRepo->nextPositionForBoard($board));

        $this->em->persist($column);
        $this->em->flush();

        $this->addFlash('success', 'Column created.');

        return $this->redirectToRoute('kanban_board', ['id' => $board->getId()]);
    }

    #[Route('/columns/{id}/rename', name: 'kanban_column_rename', methods: ['POST'])]
    public function renameColumn(Column $column, Request $request): Response
    {
        $board = $column->getBoard();
        if (!$board) {
            throw $this->createNotFoundException('Board not found.');
        }

        $token = (string)$request->request->get('_token');
        if (!$this->isCsrfTokenValid('column_rename'.$column->getId(), $token)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $request->isXmlHttpRequest()
                ? new JsonResponse(['error' => 'Invalid CSRF token.'], 403)
                : $this->redirectToRoute('kanban_board', ['id' => $board->getId()]);
        }

        $title = trim((string)$request->request->get('title'));
        if ($title === '') {
            $this->addFlash('error', 'Provide a column name.');
            return $request->isXmlHttpRequest()
                ? new JsonResponse(['error' => 'Provide a column name.'], 422)
                : $this->redirectToRoute('kanban_board', ['id' => $board->getId()]);
        }

        $column->setTitle($title);
        $this->em->flush();
        $this->addFlash('success', 'Column updated.');

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['ok' => true, 'title' => $column->getTitle()]);
        }

        return $this->redirectToRoute('kanban_board', ['id' => $board->getId()]);
    }

    #[Route('/columns/{id}/delete', name: 'kanban_column_delete', methods: ['POST'])]
    public function deleteColumn(Column $column, Request $request): Response
    {
        $board = $column->getBoard();
        if (!$board) {
            throw $this->createNotFoundException('Board not found.');
        }

        $token = (string)$request->request->get('_token');
        if (!$this->isCsrfTokenValid('column_delete'.$column->getId(), $token)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('kanban_board', ['id' => $board->getId()]);
        }

        $boardId = $board->getId();

        $this->em->wrapInTransaction(function () use ($column, $board) {
            $this->em->remove($column);
            $this->em->flush();

            $remaining = $this->colRepo->findByBoardOrdered($board);
            foreach ($remaining as $index => $col) {
                $col->setPosition($index);
            }

            $this->em->flush();
        });

        $this->addFlash('success', 'Column deleted.');

        return $this->redirectToRoute('kanban_board', ['id' => $boardId]);
    }

    #[Route('/columns/{id}/cards', name: 'kanban_card_add', methods: ['POST'])]
    public function addCard(Column $column, Request $request): Response
    {
        $board = $column->getBoard();
        if (!$board) {
            throw $this->createNotFoundException('Board not found.');
        }

        $token = (string)$request->request->get('_token');
        if (!$this->isCsrfTokenValid('card_add'.$column->getId(), $token)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('kanban_board', ['id' => $board->getId()]);
        }

        $title = trim((string)$request->request->get('title'));
        $description = trim((string)$request->request->get('description'));

        if ($title === '') {
            $this->addFlash('error', 'Provide a card title.');
            return $this->redirectToRoute('kanban_board', ['id' => $board->getId()]);
        }

        $card = (new Card())
            ->setParentColumn($column)
            ->setTitle($title)
            ->setDescription($description !== '' ? $description : null)
            ->setPosition($this->cardRepo->nextPositionForColumn($column));

        $this->em->persist($card);
        $this->em->flush();

        $this->addFlash('success', 'Card created.');

        return $this->redirectToRoute('kanban_board', ['id' => $board->getId(), '_fragment' => 'card-'.$card->getId()]);
    }

    #[Route('/cards/{id}/rename', name: 'kanban_card_rename', methods: ['POST'])]
    public function renameCard(Card $card, Request $request): Response
    {
        $column = $card->getParentColumn();
        $board = $column?->getBoard();
        if (!$column || !$board) {
            throw $this->createNotFoundException('Card or parent column not found.');
        }

        $token = (string)$request->request->get('_token');
        if (!$this->isCsrfTokenValid('card_rename'.$card->getId(), $token)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $request->isXmlHttpRequest()
                ? new JsonResponse(['error' => 'Invalid CSRF token.'], 403)
                : $this->redirectToRoute('kanban_board', ['id' => $board->getId()]);
        }

        $title = trim((string)$request->request->get('title'));
        $description = trim((string)$request->request->get('description'));

        if ($title === '') {
            $this->addFlash('error', 'Provide a card title.');
            return $request->isXmlHttpRequest()
                ? new JsonResponse(['error' => 'Provide a card title.'], 422)
                : $this->redirectToRoute('kanban_board', ['id' => $board->getId()]);
        }

        $card->setTitle($title);
        $card->setDescription($description !== '' ? $description : null);
        $this->em->flush();
        $this->addFlash('success', 'Card updated.');

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'ok' => true,
                'title' => $card->getTitle(),
                'description' => $card->getDescription() ?? ''
            ]);
        }

        return $this->redirectToRoute('kanban_board', ['id' => $board->getId(), '_fragment' => 'card-'.$card->getId()]);
    }

    #[Route('/cards/{id}/delete', name: 'kanban_card_delete', methods: ['POST'])]
    public function deleteCard(Card $card, Request $request): Response
    {
        $column = $card->getParentColumn();
        $board = $column?->getBoard();
        if (!$column || !$board) {
            throw $this->createNotFoundException('Card or parent column not found.');
        }

        $token = (string)$request->request->get('_token');
        if (!$this->isCsrfTokenValid('card_delete'.$card->getId(), $token)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('kanban_board', ['id' => $board->getId()]);
        }

        $boardId = $board->getId();

        $this->em->wrapInTransaction(function () use ($card, $column) {
            $this->em->remove($card);
            $this->em->flush();

            $remaining = $this->cardRepo->findByColumnOrdered($column);
            foreach ($remaining as $index => $c) {
                $c->setPosition($index);
            }

            $this->em->flush();
        });

        $this->addFlash('success', 'Card deleted.');

        return $this->redirectToRoute('kanban_board', ['id' => $boardId]);
    }
}
