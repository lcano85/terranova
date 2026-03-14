<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';

class AttendanceController extends Controller
{
  public function mark(): void
  {
    Auth::requireRole('admin');
    Helpers::redirect('/admin/attendance');
  }
}
