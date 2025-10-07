<?php

// src/Controller/SidebarController.php
namespace App\Controller;

use App\Entity\Board;
use App\Repository\BoardRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class SidebarController extends AbstractController
{
    public function __construct(
        private BoardRepository $boards,
        private EntityManagerInterface $em
    ) {}

    #[Route('/_fragment/sidebar/boards', name: 'sidebar_boards', methods: ['GET'])]
    public function boards(): Response
    {
        // display all boards in sidebar
        $all = $this->boards->createQueryBuilder('b')
            ->orderBy('b.id', 'DESC')
            ->getQuery()->getResult();

        return $this->render('components/sidebar.html.twig', [
            'boards' => $all,
        ]);
    }

    #[Route('/boards/create', name: 'board_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $token = (string)$request->request->get('_token');
        if (!$this->isCsrfTokenValid('create_board', $token)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirect($request->headers->get('referer') ?? '/');
        }

        $name = trim((string)$request->request->get('name'));
        if ($name === '') {
            $this->addFlash('error', 'Provide a board name.');
            return $this->redirect($request->headers->get('referer') ?? '/');
        }

        $board = (new Board())->setName($name);
        $this->em->persist($board);
        $this->em->flush();

        // redirect to board after creation so the user can start working immediately
        return $this->redirectToRoute('kanban_board', ['id' => $board->getId()]);
    }
    
    #[Route('/api/boards', name: 'api_board_create', methods: ['POST'])]
    public function createApi(Request $req, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($req->getContent(), true) ?? [];
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            return new JsonResponse(['error' => 'name required'], 422);
        }
        $board = (new Board())->setName($name);
        $em->persist($board);
        $em->flush();

        return new JsonResponse(['id'=>$board->getId(), 'name'=>$board->getName()], 201);
    }
}
