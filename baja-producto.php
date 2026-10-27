<?php

require __DIR__ . '/php/sesion.php';

if (empty($_SESSION['usuario_id'])) {
    header('Location: index.html');
    exit;
}

require __DIR__ . '/php/conexion.php';

function escapar($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$idEntrada = $_GET['id'] ?? null;

$id = is_string($idEntrada)
    ? filter_var(
        $idEntrada,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    )
    : false;

if ($id === false || $id === null) {
    http_response_code(400);
    exit('El identificador del producto no es válido.');
}

$consulta = $pdo->prepare(
    'SELECT id, codigo, nombre, existencias
     FROM productos
     WHERE id = ? AND activo = 1'
);

$consulta->execute([$id]);
$producto = $consulta->fetch();

if (!$producto) {
    http_response_code(404);
    exit('El producto no existe o ya está dado de baja.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf'], $token)
    ) {
        http_response_code(403);
        exit('Solicitud inválida. Recarga la página.');
    }

    try {
        $consulta = $pdo->prepare(
            'UPDATE productos
             SET activo = 0
             WHERE id = ? AND activo = 1'
        );

        $consulta->execute([$id]);

        $_SESSION['mensaje_inventario'] = 'Producto dado de baja correctamente.';

        header('Location: inventario.php');
        exit;
    } catch (PDOException $excepcion) {
        error_log($excepcion->getMessage());
        $error = 'No fue posible dar de baja el producto. Intenta nuevamente.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dar de baja | Retail Corp</title>
  <link rel="stylesheet" href="css/estilos.css">
  <link rel="stylesheet" href="css/inventario.css">
</head>
<body class="pagina-inventario">
  <header class="inventario-header">
    <div class="inventario-marca">
      <img src="img/logo.png" alt="Logo de Retail Corp">

      <div>
        <strong>Retail Corp</strong>
        <span>Control de inventario</span>
      </div>
    </div>

    <a href="inventario.php" class="boton-salir">
      Volver al inventario
    </a>
  </header>

  <main class="inventario-contenedor">
    <div class="inventario-titulo">
      <p class="etiqueta etiqueta-oscura">ADMINISTRACIÓN</p>
      <h1>Dar de baja un producto</h1>
      <p>Revisa el producto antes de confirmar.</p>
    </div>

    <?php if ($error !== ''): ?>
      <p class="aviso aviso-error" role="alert">
        <?= escapar($error) ?>
      </p>
    <?php endif; ?>

    <section class="panel-inventario panel-confirmacion">
      <h2><?= escapar($producto['nombre']) ?></h2>

      <dl class="producto-detalles">
        <div>
          <dt>Código</dt>
          <dd><?= escapar($producto['codigo']) ?></dd>
        </div>

        <div>
          <dt>Existencias registradas</dt>
          <dd><?= (int) $producto['existencias'] ?> unidades</dd>
        </div>
      </dl>

      <p class="explicacion-baja">
        El producto dejará de mostrarse en el inventario activo.
        Sus datos y su cantidad registrada se conservarán en la base de datos.
      </p>

      <form
        action="baja-producto.php?id=<?= (int) $id ?>"
        method="POST"
        class="acciones-edicion"
      >
        <input
          type="hidden"
          name="csrf"
          value="<?= escapar($_SESSION['csrf']) ?>"
        >

        <button type="submit" class="boton-confirmar-baja">
          Confirmar baja
        </button>

        <a href="inventario.php" class="boton-cancelar">
          Cancelar
        </a>
      </form>
    </section>
  </main>
</body>
</html>