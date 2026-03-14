<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Csrf.php';
require_once __DIR__ . '/../models/User.php';

class AuthController extends Controller
{
  public function login(): void
  {
    if (Auth::check()) {
      $u = Auth::user();
      Helpers::redirect(($u['role'] === 'admin') ? '/admin' : '/worker');
    }

    $error = null;

    if (Helpers::isPost()) {
      Csrf::check();

      $docType = $_POST['document_type'] ?? '';
      $docNumber = trim($_POST['document_number'] ?? '');
      $pass = $_POST['password'] ?? '';

      $u = User::findByDoc($docType, $docNumber);

      if (!$u || !password_verify($pass, $u['password_hash'])) {
        $error = "Credenciales inválidas";
        $this->view('auth/login', compact('error'));
        return; // ✅ return sin valor
      }


      Auth::login($u);
      Helpers::redirect(($u['role'] === 'admin') ? '/admin' : '/worker');
    }

    $this->view('auth/login', compact('error'));
  }

  public function logout(): void
  {
    Auth::logout();
    Helpers::redirect('/login');
  }
}
