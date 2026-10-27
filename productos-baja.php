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

    $idEntrada = $_POST['id'] ?? null;

    $id = is_string($idEntrada)
        ? filter_var(
            $idEntrada,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        )
        : false;

    if ($id === false || $id === null) {
        $error = 'El identificador del producto no es válido.';
    } else {
        try {
            $consulta = $pdo->prepare(
                'UPDATE productos
                 SET activo = 1
                 WHERE id = ? AND activo = 0'
            );

            $consulta->execute([$id]);

            if ($consulta->rowCount() === 1) {
                $_SESSION['mensaje_inventario'] =
                    'Producto reactivado correctamente. Revisa sus existencias.';

                header('Location: inventario.php');
                exit;
            }

            $error = 'El producto no existe o ya está activo.';
        } catch (PDOException $excepcion) {
            error_log($excepcion->getMessage());
            $error = 'No fue posible reactivar el producto.';
        }
    }
}

$productos = $pdo->query(
    'SELECT id, codigo, nombre, marca, existencias, ubicacion, imagen
     FROM productos
     WHERE activo = 0
     ORDER BY actualizado_en DESC, id DESC'
)->fetchAll();

$imagenesPermitidas = [];

for ($numero = 1; $numero <= 11; $numero++) {
    $imagenesPermitidas[] = sprintf(
        'img/productos/producto-%02d.jpeg',
        $numero
    );
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Productos dados de baja | Retail Corp</title>
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
      <h1>Productos dados de baja</h1>
      <p>
        Reactiva un producto para que vuelva a aparecer
        en el inventario activo.
      </p>
    </div>

    <?php if ($error !== ''): ?>
      <p class="aviso aviso-error" role="alert">
        <?= escapar($error) ?>
      </p>
    <?php endif; ?>

    <p class="texto-secundario">
      Productos dados de baja: <?= count($productos) ?>
    </p>

    <?php if (!$productos): ?>
      <div class="inventario-vacio">
        No hay productos dados de baja.
      </div>
    <?php else: ?>
      <div class="catalogo-productos">
        <?php foreach ($productos as $producto): ?>
          <article class="producto-tarjeta">
            <div class="producto-foto">
              <?php if (
                  in_array($producto['imagen'], $imagenesPermitidas, true)
              ): ?>
                <img
                  src="<?= escapar($producto['imagen']) ?>"
                  alt="<?= escapar($producto['nombre']) ?>"
                  loading="lazy"
                >
              <?php else: ?>
                <span>Sin fotografía</span>
              <?php endif; ?>
            </div>

            <div class="producto-informacion">
              <span class="producto-codigo">
                <?= escapar($producto['codigo']) ?>
              </span>

              <h2 class="producto-nombre-baja">
                <?= escapar($producto['nombre']) ?>
              </h2>

              <p class="texto-secundario">
                <?= escapar($producto['marca']) ?>
              </p>

              <dl class="producto-detalles">
                <div>
                  <dt>Existencias registradas</dt>
                  <dd><?= (int) $producto['existencias'] ?></dd>
                </div>

                <div>
                  <dt>Ubicación</dt>
                  <dd>
                    <?= escapar($producto['ubicacion'] ?: 'Sin asignar') ?>
                  </dd>
                </div>
              </dl>

              <span class="estado-stock stock-bajo">
                Dado de baja
              </span>

              <form
                action="productos-baja.php"
                method="POST"
                class="formulario-reactivar"
              >
                <input
                  type="hidden"
                  name="csrf"
                  value="<?= escapar($_SESSION['csrf']) ?>"
                >

                <input
                  type="hidden"
                  name="id"
                  value="<?= (int) $producto['id'] ?>"
                >

                <button
                  type="submit"
                  class="boton-guardar boton-reactivar"
                  aria-label="<?= escapar(
                      'Reactivar ' . $producto['nombre']
                  ) ?>"
                >
                  Reactivar producto
                </button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>