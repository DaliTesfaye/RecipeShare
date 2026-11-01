<?php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RegisterController extends AbstractController
{
    #[Route('/api/register', name: 'app_api_register', methods: ['POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        try {
            $data = $request->toArray();
        } catch (\Symfony\Component\HttpFoundation\Exception\JsonException) {
            return $this->json(['error' => 'Invalid JSON body.'], 400);
        }

        $name = trim($data['name'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';

        if ($name === '' || strlen($name) > 100) {
            return $this->json([
                'error' => 'Name is required and must be at most 100 characters.',
            ], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 180) {
            return $this->json(['error' => 'A valid email is required.'], 400);
        }

        if (!is_string($password) || strlen($password) < 8) {
            return $this->json([
                'error' => 'Password must contain at least 8 characters.',
            ], 400);
        }

        if ($entityManager->getRepository(User::class)->findOneBy(['email' => $email])) {
            return $this->json(['error' => 'An account with this email already exists.'], 409);
        }

        $user = new User();
        $user->setName($name);
        $user->setEmail($email);
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json([
            'message' => 'Account created successfully.',
            'user' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
            ],
        ], 201);
    }
}
