<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class SessionController extends AbstractController
{
    #[Route('/clear-session', name: 'clear_session_simple', methods: ['GET'])]
    public function clearSimple(SessionInterface $session): Response
    {
        // Быстрая очистка сессии без лишних сообщений
        $session->clear();
        $session->invalidate();

        // Возвращаем простой HTML с JavaScript для очистки
        return new Response('
<!DOCTYPE html>
<html>
<head>
    <title>Session Cleared</title>
    <meta http-equiv="refresh" content="2;url=/">
</head>
<body style="font-family: Arial, sans-serif; text-align: center; padding: 50px;">
    <h1>✅ Сессия очищена</h1>
    <p>Перенаправление на главную страницу через 2 секунды...</p>
    <p><a href="/">Перейти сейчас</a></p>
    <script>
        // Очищаем локальное хранилище браузера тоже
        localStorage.clear();
        sessionStorage.clear();
    </script>
</body>
</html>
        ', 200, ['Content-Type' => 'text/html']);
    }

    #[Route('/session/clear', name: 'session_clear', methods: ['GET', 'POST'])]
    public function clear(Request $request, SessionInterface $session): Response
    {
        // Сохраняем информацию о том, что сессия была очищена
        $wasLoggedIn = $this->getUser() !== null;
        $sessionData = $session->all();

        // Полная очистка сессии
        $session->clear();
        $session->invalidate();

        // Создаем флеш-сообщение
        $this->addFlash('success', 'Сессия успешно очищена');

        // Если пользователь был авторизован, показываем дополнительное сообщение
        if ($wasLoggedIn) {
            $this->addFlash('info', 'Вы были автоматически разлогинены');
        }

        // Перенаправляем на домашнюю страницу или страницу, с которой пришел пользователь
        $referer = $request->headers->get('referer');
        if ($referer && parse_url($referer, PHP_URL_HOST) === $request->getHost()) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('catalog_index');
    }

    #[Route('/session/clear-key/{key}', name: 'session_clear_key', methods: ['POST'])]
    public function clearKey(string $key, SessionInterface $session): Response
    {
        if ($session->has($key)) {
            $session->remove($key);
            $this->addFlash('success', sprintf('Ключ сессии "%s" успешно удален', $key));
        } else {
            $this->addFlash('warning', sprintf('Ключ сессии "%s" не найден', $key));
        }

        return $this->redirectToRoute('catalog_index');
    }

    #[Route('/session/info', name: 'session_info', methods: ['GET'])]
    public function info(SessionInterface $session): Response
    {
        return $this->json([
            'session_id' => $session->getId(),
            'session_keys' => array_keys($session->all()),
            'session_data_size' => strlen(serialize($session->all())),
            'is_started' => $session->isStarted(),
            'user' => $this->getUser() ? 'authenticated' : null,
        ]);
    }

    #[Route('/admin/session/clear-all', name: 'admin_session_clear_all', methods: ['POST'])]
    public function clearAllSessions(): Response
    {
        // Проверяем права администратора
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Получаем путь к директории сессий
        $sessionPath = $this->getParameter('kernel.project_dir') . '/var/sessions';

        if (is_dir($sessionPath)) {
            $files = glob($sessionPath . '/*');
            $count = 0;

            foreach ($files as $file) {
                if (is_file($file) && unlink($file)) {
                    $count++;
                }
            }

            $this->addFlash('success', sprintf('Удалено %d файлов сессий', $count));
        } else {
            $this->addFlash('warning', 'Директория сессий не найдена');
        }

        return $this->redirectToRoute('admin_dashboard');
    }
}
