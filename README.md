# RecipeShare

A recipe-sharing web application developed as an academic mini-project at ISET Rades for the 2026/2027 academic year.

## Team

- **Dridi Med Ali** — Backend & Database
- **Islem Khalfallah** — Frontend & UI/UX

## Technologies

### Frontend
- React
- Vite
- Axios
- React Router

### Backend
- Symfony
- PHP
- Doctrine ORM
- MySQL
- Symfony Security

### Development Tools
- Docker Compose
- Git and GitHub

## Project Structure

```text
RecipeShare/
├── backend/       # Symfony REST API
├── frontend/      # React application
├── docs/          # Diagrams and documentation
├── compose.yaml   # Docker services
├── .gitignore
└── README.md
```

## Prerequisites

- PHP and Composer
- Node.js and npm
- Docker Desktop
- Git

## Getting Started

### 1. Start the database

From the project root:

```bash
docker compose up -d
```

Check the database container:

```bash
docker compose ps
```

### 2. Start the backend

```bash
cd backend
composer install
php bin/console about
```

### 3. Start the frontend

Open another terminal:

```bash
cd frontend
npm install
npm run dev
```

Vite will display the local URL for the frontend.

## Project Status

Initial development environment setup is in progress. Features and documentation will be added incrementally during development.
