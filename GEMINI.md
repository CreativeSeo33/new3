# GEMINI.md: Project Overview

## 1. Project Overview

This is a sophisticated e-commerce platform built on the **Symfony** framework (version 7.3). The project features a decoupled frontend architecture with a strong emphasis on performance and modularity.

### Core Technologies:

*   **Backend:** PHP 8.2, Symfony 7.3, API Platform, Doctrine ORM, LexikJWTAuthenticationBundle, TNT Search.
*   **Frontend:**
    *   **Admin Panel:** A Single Page Application (SPA) built with **Vue.js 3**, TypeScript, Pinia for state management, and styled with Tailwind CSS.
    *   **Catalog (Storefront):** A server-rendered application using **Twig** templates, enhanced with **Stimulus** for interactivity and **Turbo** for fast navigation. It features a powerful theming system.
*   **Build & Asset Management:** Webpack Encore for asset bundling, with separate builds for the admin and catalog applications.
*   **Containerization:** Docker is used for creating a consistent development and production environment.

### Key Architectural Features:

*   **Dual Frontend:** The project separates the administrative backend (as a Vue.js SPA) from the customer-facing catalog (a Symfony UX-powered application). This allows for tailored development experiences for each part of the application.
*   **API-Driven:** The use of API Platform and JWT authentication indicates that the backend exposes a comprehensive API for the admin SPA and potentially for other clients.
*   **Themable Storefront:** The catalog is designed to be themable, allowing for easy customization of the store's appearance. The build process dynamically includes themes from the `/themes` directory.
*   **Performance Optimization:** The project includes custom tools like the `app:images:cache:warmup` command to handle performance-critical tasks like image processing in a high-volume environment.

## 2. Building and Running

The project uses `npm` for frontend dependencies and `composer` for backend dependencies.

### Frontend Commands:

The `package.json` file defines scripts for building and running the two separate frontend applications.

*   **Run Admin Development Server:**
    ```bash
    npm run dev:admin
    ```
*   **Build Admin for Production:**
    ```bash
    npm run build:admin
    ```
*   **Run Catalog Development Server:**
    ```bash
    npm run dev:catalog
    ```
*   **Build Catalog for Production:**
    ```bash
    npm run build:catalog
    ```
*   **Run All Development Servers:**
    ```bash
    npm run watch
    ```
*   **Build All for Production:**
    ```bash
    npm run build
    ```

### Backend Commands (Symfony):

*   **Run Image Cache Warmer:** (A key feature of this project)
    ```bash
    php bin/console app:images:cache:warmup
    ```
*   **Run Static Analysis:**
    ```bash
    composer stan
    ```
*   **Run Tests:**
    ```bash
    # (TODO: Confirm test command, likely using PHPUnit)
    vendor/bin/phpunit
    ```

### Docker:

*   **Start Docker Containers:**
    ```bash
    docker-compose up -d
    ```
*   **Stop Docker Containers:**
    ```bash
    docker-compose down
    ```

## 3. Development Conventions

*   **TypeScript:** The entire frontend, including both the Vue.js admin and the Stimulus catalog, is written in TypeScript.
*   **Styling:** Tailwind CSS is the primary CSS framework.
*   **Static Analysis:** PHPStan is configured for static analysis of the PHP code.
*   **Modular Frontend (Catalog):** The catalog's frontend code is structured into modules (e.g., `@shared`, `@features`, `@entities`), promoting code reuse and maintainability.
*   **Component-Based UI:** The project uses Storybook for developing and documenting UI components for both the admin and catalog applications, indicating a component-driven development approach.

# Инструкции для Gemini

Всегда отвечай и генерируй весь текст **только на русском языке**. Сохраняй технический, но дружелюбный тон.
