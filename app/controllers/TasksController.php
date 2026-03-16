<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/Task.php';

class TasksController extends Controller
{
  public function board(): void
  {
    $board = Task::weeklyBoard();
    $this->view('tasks/board', compact('board'));
  }
}
