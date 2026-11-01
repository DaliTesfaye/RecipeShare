<?php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileController extends AbstractController
{
    #[Route('/api/profile', name: 'app_api_profile', methods: ['GET'])]
    public function index(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'id' => $user->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'bio' => $user->getBio(),
        ]);
    }

    #[Route('/api/profile', name: 'app_api_profile_update', methods: ['PATCH'])]
    public function updateProfile(
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $data = $request->toArray();
        } catch (\Symfony\Component\HttpFoundation\Exception\JsonException) {
            return $this->json([
                'error' => 'Invalid JSON body.',
            ], 400);
        }

        // Validate name if provided
        if (array_key_exists('name', $data)) {
            if (!is_string($data['name'])) {
                return $this->json(['error' => 'Name must be a string.'], 400);
            }

            $name = trim($data['name']);

            if ($name === '' || strlen($name) > 100) {
                return $this->json([
                    'error' => 'Name is required and must be at most 100 characters.',
                ], 400);
            }

            $user->setName($name);
        }

        // Validate bio if provided
        if (array_key_exists('bio', $data)) {
            if ($data['bio'] !== null && !is_string($data['bio'])) {
                return $this->json(['error' => 'Bio must be a string or null.'], 400);
            }

            $bio = $data['bio'] === null ? null : trim($data['bio']);

            if ($bio !== null && strlen($bio) > 500) {
                return $this->json([
                    'error' => 'Bio must not exceed 500 characters.',
                ], 400);
            }

            $user->setBio($bio);
        }

        if (!array_key_exists('name', $data) && !array_key_exists('bio', $data)) {
            return $this->json([
                'error' => 'Provide at least one field to update.',
            ], 400);
        }

        // Save changes to the database
        $entityManager->flush();

        return $this->json([
            'message' => 'Profile updated successfully.',
            'user' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'bio' => $user->getBio(),
            ],
        ]);
    }
}
