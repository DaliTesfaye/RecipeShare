<?php

namespace App\Controller\Api;

use App\Entity\Ingredient;
use App\Entity\Recipe;
use App\Entity\RecipeStep;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class RecipeController extends AbstractController
{
    // GET: Browse all recipes
    #[Route('/api/recipes', name: 'app_api_recipes_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): JsonResponse
    {
        $recipes = $entityManager->getRepository(Recipe::class)
            ->findBy([], ['createdAt' => 'DESC']);

        $data = array_map(
            fn (Recipe $recipe) => $this->formatRecipe($recipe),
            $recipes
        );

        return $this->json([
            'recipes' => $data,
            'total' => count($data),
        ]);
    }

    // POST: Create a recipe
    #[Route('/api/recipes', name: 'app_api_recipes_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized.'], 401);
        }

        try {
            $data = $request->toArray();
        } catch (\Symfony\Component\HttpFoundation\Exception\JsonException) {
            return $this->json(['error' => 'Invalid JSON body.'], 400);
        }

        $requiredFields = [
            'title', 'description', 'category', 'difficulty',
            'preparationTime', 'cookingTime', 'servings',
            'ingredients', 'steps',
        ];

        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data)) {
                return $this->json([
                    'error' => "The field '$field' is required.",
                ], 400);
            }
        }

        $error = $this->validateRecipeData($data);

        if ($error !== null) {
            return $this->json(['error' => $error], 400);
        }

        $recipe = new Recipe();

        $recipe
            ->setTitle(trim($data['title']))
            ->setDescription(trim($data['description']))
            ->setCategory(trim($data['category']))
            ->setDifficulty(trim($data['difficulty']))
            ->setPreparationTime($data['preparationTime'])
            ->setCookingTime($data['cookingTime'])
            ->setServings($data['servings'])
            ->setImage(
                isset($data['image']) ? trim($data['image']) : null
            )
            ->setUser($user);

        $entityManager->persist($recipe);

        // Save ingredients
        foreach ($data['ingredients'] as $ingredientData) {
            $ingredient = new Ingredient();

            $ingredient
                ->setName(trim($ingredientData['name']))
                ->setQuantity(trim($ingredientData['quantity']))
                ->setRecipe($recipe);

            $entityManager->persist($ingredient);
        }

        // Save preparation steps in order
        foreach ($data['steps'] as $index => $instruction) {
            $step = new RecipeStep();

            $step
                ->setInstruction(trim($instruction))
                ->setStepNumber($index + 1)
                ->setRecipe($recipe);

            $entityManager->persist($step);
        }

        $entityManager->flush();

        return $this->json([
            'message' => 'Recipe created successfully.',
            'recipe' => $this->formatRecipeDetails($recipe, $entityManager),
        ], 201);
    }

    // GET: Retrieve one recipe with ingredients and steps
    #[Route('/api/recipes/{id}', name: 'app_api_recipes_show', methods: ['GET'])]
    public function show(
        int $id,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $recipe = $entityManager->getRepository(Recipe::class)->find($id);

        if (!$recipe) {
            return $this->json(['error' => 'Recipe not found.'], 404);
        }

        return $this->json([
            'recipe' => $this->formatRecipeDetails($recipe, $entityManager),
        ]);
    }

    // PUT: Update an existing recipe
    #[Route('/api/recipes/{id}', name: 'app_api_recipes_update', methods: ['PUT'])]
    public function update(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized.'], 401);
        }

        $recipe = $entityManager->getRepository(Recipe::class)->find($id);

        if (!$recipe) {
            return $this->json(['error' => 'Recipe not found.'], 404);
        }

        // Only the recipe owner can update it
        if ($recipe->getUser()->getId() !== $user->getId()) {
            return $this->json([
                'error' => 'You are not allowed to update this recipe.',
            ], 403);
        }

        try {
            $data = $request->toArray();
        } catch (\Symfony\Component\HttpFoundation\Exception\JsonException) {
            return $this->json(['error' => 'Invalid JSON body.'], 400);
        }

        $error = $this->validateRecipeData($data, false);

        if ($error !== null) {
            return $this->json(['error' => $error], 400);
        }

        // Update basic recipe fields
        $stringFields = [
            'title' => 'setTitle',
            'description' => 'setDescription',
            'category' => 'setCategory',
            'difficulty' => 'setDifficulty',
        ];

        foreach ($stringFields as $field => $setter) {
            if (array_key_exists($field, $data)) {
                $recipe->$setter(trim($data[$field]));
            }
        }

        $numericFields = [
            'preparationTime' => 'setPreparationTime',
            'cookingTime' => 'setCookingTime',
            'servings' => 'setServings',
        ];

        foreach ($numericFields as $field => $setter) {
            if (array_key_exists($field, $data)) {
                $recipe->$setter($data[$field]);
            }
        }

        if (array_key_exists('image', $data)) {
            $recipe->setImage(
                $data['image'] === null ? null : trim($data['image'])
            );
        }

        // Replace ingredients if provided
        if (array_key_exists('ingredients', $data)) {
            $oldIngredients = $entityManager
                ->getRepository(Ingredient::class)
                ->findBy(['recipe' => $recipe]);

            foreach ($oldIngredients as $oldIngredient) {
                $entityManager->remove($oldIngredient);
            }

            foreach ($data['ingredients'] as $ingredientData) {
                $ingredient = new Ingredient();

                $ingredient
                    ->setName(trim($ingredientData['name']))
                    ->setQuantity(trim($ingredientData['quantity']))
                    ->setRecipe($recipe);

                $entityManager->persist($ingredient);
            }
        }

        // Replace preparation steps if provided
        if (array_key_exists('steps', $data)) {
            $oldSteps = $entityManager
                ->getRepository(RecipeStep::class)
                ->findBy(['recipe' => $recipe]);

            foreach ($oldSteps as $oldStep) {
                $entityManager->remove($oldStep);
            }

            foreach ($data['steps'] as $index => $instruction) {
                $step = new RecipeStep();

                $step
                    ->setInstruction(trim($instruction))
                    ->setStepNumber($index + 1)
                    ->setRecipe($recipe);

                $entityManager->persist($step);
            }
        }

        $entityManager->flush();

        return $this->json([
            'message' => 'Recipe updated successfully.',
            'recipe' => $this->formatRecipeDetails($recipe, $entityManager),
        ]);
    }

    // Validate recipe input
    private function validateRecipeData(
        array $data,
        bool $requireAll = true
    ): ?string {
        $stringFields = [
            'title' => 150,
            'description' => null,
            'category' => 100,
            'difficulty' => 30,
        ];

        foreach ($stringFields as $field => $maxLength) {
            if (!array_key_exists($field, $data)) {
                if ($requireAll) {
                    return "The field '$field' is required.";
                }
                continue;
            }

            if (!is_string($data[$field]) || trim($data[$field]) === '') {
                return "$field must be a non-empty string.";
            }

            if ($maxLength !== null && strlen($data[$field]) > $maxLength) {
                return "$field must not exceed $maxLength characters.";
            }
        }

        foreach (['preparationTime', 'cookingTime', 'servings'] as $field) {
            if (!array_key_exists($field, $data)) {
                if ($requireAll) {
                    return "The field '$field' is required.";
                }
                continue;
            }

            if (!is_int($data[$field]) || $data[$field] < 1) {
                return "$field must be a positive integer.";
            }
        }

        if (array_key_exists('image', $data)) {
            if (
                $data['image'] !== null
                && (
                    !is_string($data['image'])
                    || strlen($data['image']) > 255
                )
            ) {
                return 'Image must be a string of at most 255 characters or null.';
            }
        }

        if (!array_key_exists('ingredients', $data)) {
            if ($requireAll) {
                return 'The field ingredients is required.';
            }
        } else {
            if (!is_array($data['ingredients'])) {
                return 'Ingredients must be an array.';
            }

            foreach ($data['ingredients'] as $ingredient) {
                if (
                    !is_array($ingredient)
                    || !isset($ingredient['name'], $ingredient['quantity'])
                    || !is_string($ingredient['name'])
                    || !is_string($ingredient['quantity'])
                    || trim($ingredient['name']) === ''
                    || trim($ingredient['quantity']) === ''
                    || strlen($ingredient['name']) > 100
                    || strlen($ingredient['quantity']) > 100
                ) {
                    return 'Each ingredient needs a valid name and quantity (maximum 100 characters each).';
                }
            }
        }

        if (!array_key_exists('steps', $data)) {
            if ($requireAll) {
                return 'The field steps is required.';
            }
        } else {
            if (!is_array($data['steps'])) {
                return 'Steps must be an array.';
            }

            foreach ($data['steps'] as $step) {
                if (!is_string($step) || trim($step) === '') {
                    return 'Every preparation step must be a non-empty string.';
                }
            }
        }

        return null;
    }

    // Format basic recipe information
    private function formatRecipe(Recipe $recipe): array
    {
        return [
            'id' => $recipe->getId(),
            'title' => $recipe->getTitle(),
            'description' => $recipe->getDescription(),
            'category' => $recipe->getCategory(),
            'difficulty' => $recipe->getDifficulty(),
            'preparationTime' => $recipe->getPreparationTime(),
            'cookingTime' => $recipe->getCookingTime(),
            'servings' => $recipe->getServings(),
            'image' => $recipe->getImage(),
            'createdAt' => $recipe->getCreatedAt()->format('Y-m-d H:i:s'),
            'author' => [
                'id' => $recipe->getUser()->getId(),
                'name' => $recipe->getUser()->getName(),
            ],
        ];
    }

    // Format complete recipe information
    private function formatRecipeDetails(
        Recipe $recipe,
        EntityManagerInterface $entityManager
    ): array {
        $data = $this->formatRecipe($recipe);

        $ingredients = $entityManager
            ->getRepository(Ingredient::class)
            ->findBy(['recipe' => $recipe], ['id' => 'ASC']);

        $steps = $entityManager
            ->getRepository(RecipeStep::class)
            ->findBy(['recipe' => $recipe], ['stepNumber' => 'ASC']);

        $data['ingredients'] = array_map(
            fn (Ingredient $ingredient) => [
                'id' => $ingredient->getId(),
                'name' => $ingredient->getName(),
                'quantity' => $ingredient->getQuantity(),
            ],
            $ingredients
        );

        $data['steps'] = array_map(
            fn (RecipeStep $step) => [
                'id' => $step->getId(),
                'stepNumber' => $step->getStepNumber(),
                'instruction' => $step->getInstruction(),
            ],
            $steps
        );

        return $data;
    }
}
