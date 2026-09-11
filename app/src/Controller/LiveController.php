<?php

namespace App\Controller;

use App\Client\ClientStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LiveController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(Request $request, ClientStore $clients): Response
    {
        $response = $this->render('index.html.twig', ['clients' => $clients->all()]);

        // The disclosure rate limiter keys on this cookie, so each visitor
        // gets an isolated budget across page reloads. Demo only: in a real
        // app you would key on the authenticated user.
        if ($request->query->has('r')) {
            $response->headers->setCookie(new Cookie('disclose_subject', (string) $request->query->get('r'), 0, '/', null, false, true));
        }

        return $response;
    }
}
