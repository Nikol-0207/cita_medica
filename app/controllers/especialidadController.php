<?php
require_once __DIR__ . '/../models/Especialidad.php';
require_once __DIR__ . '/../models/Configuracion.php';
require_once __DIR__ . '/../helpers/Permisos.php';

class EspecialidadController {
    private $model;

    public function __construct() {
        $this->model = new Especialidad();
    }

    public function listar() {
        Permisos::exigir('especialidades.ver');

        $colores        = (new Configuracion())->getColoresPersonalizados();
        require_once __DIR__ . '/../helpers/Paginador.php';
        $pag            = Paginador::cortar($this->model->listarActivas());
        $especialidades = $pag['items'];
        $mensajeExito   = $_SESSION['mensaje_exito'] ?? null;
        $mensajeError   = $_SESSION['mensaje_error'] ?? null;
        $reabrir        = $_SESSION['reabrir'] ?? null;   // modal que debe volver a abrirse tras un error
        unset($_SESSION['mensaje_exito'], $_SESSION['mensaje_error'], $_SESSION['reabrir']);

        require_once __DIR__ . '/../views/especialidades/listar.php';
    }

    private function validar($nombre, $descripcion, $idExcluir = null) {
        $errores = [];
        if ($nombre === '') {
            $errores[] = "El nombre es obligatorio.";
        } elseif (strlen($nombre) > 100) {
            $errores[] = "El nombre no puede superar 100 caracteres.";
        } elseif ($this->model->existeNombre($nombre, $idExcluir)) {
            $errores[] = "Ya existe una especialidad con ese nombre.";
        }
        if (strlen($descripcion) > 500) {
            $errores[] = "La descripción no puede superar 500 caracteres.";
        }
        return $errores;
    }

    public function guardar() {
        Permisos::exigir('especialidades.gestionar');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?route=especialidades");
            exit;
        }

        $nombre      = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');

        $errores = $this->validar($nombre, $descripcion);
        if ($errores) {
            $_SESSION['mensaje_error'] = implode(' ', $errores);
            $_SESSION['reabrir'] = ['modal' => 'crear', 'nombre' => $nombre, 'descripcion' => $descripcion];
            header("Location: index.php?route=especialidades");
            exit;
        }

        try {
            $this->model->crear($nombre, $descripcion !== '' ? $descripcion : null);
            $_SESSION['mensaje_exito'] = "Especialidad creada correctamente.";
        } catch (Throwable $e) {
            error_log($e->getMessage());
            // TEMPORAL mientras desarrollas: muestra el error real
            $_SESSION['mensaje_error'] = "No se pudo crear la especialidad: " . $e->getMessage();
            $_SESSION['reabrir'] = ['modal' => 'crear', 'nombre' => $nombre, 'descripcion' => $descripcion];
        }

        header("Location: index.php?route=especialidades");
        exit;
    }

    public function actualizar() {
        Permisos::exigir('especialidades.gestionar');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?route=especialidades");
            exit;
        }

        $id          = (int)($_POST['id'] ?? 0);
        $nombre      = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');

        if ($id <= 0 || !$this->model->existeActiva($id)) {
            $_SESSION['mensaje_error'] = "La especialidad no existe o no está activa.";
            header("Location: index.php?route=especialidades");
            exit;
        }

        $errores = $this->validar($nombre, $descripcion, $id);
        if ($errores) {
            $_SESSION['mensaje_error'] = implode(' ', $errores);
            $_SESSION['reabrir'] = ['modal' => 'editar', 'id' => $id, 'nombre' => $nombre, 'descripcion' => $descripcion];
            header("Location: index.php?route=especialidades");
            exit;
        }

        try {
            $this->model->actualizar($id, $nombre, $descripcion !== '' ? $descripcion : null);
            $_SESSION['mensaje_exito'] = "Especialidad actualizada correctamente.";
        } catch (Throwable $e) {
            error_log($e->getMessage());
            // TEMPORAL mientras desarrollas: muestra el error real
            $_SESSION['mensaje_error'] = "No se pudo actualizar la especialidad: " . $e->getMessage();
            $_SESSION['reabrir'] = ['modal' => 'editar', 'id' => $id, 'nombre' => $nombre, 'descripcion' => $descripcion];
        }

        header("Location: index.php?route=especialidades");
        exit;
    }
}