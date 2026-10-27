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

// Fotografías disponibles para seleccionar.
$imagenes = [];

for ($numero = 1; $numero <= 11; $numero++) {
    $archivo = sprintf('producto-%02d.jpeg', $numero);
    $imagenes[$archivo] = 'img/productos/' . $archivo;
}

$datos = [
    'codigo' => '',
    'nombre' => '',
    'descripcion' => '',
    'marca' => 'Popit!',
    'capacidad' => '',
    'existencias' => '0',
    'stock_minimo' => '0',
    'ubicacion' => '',
    'imagen' => '',
];

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf'], $token)
    ) {
        http_response_code(403);
        exit('Solicitud inválida. Recarga la página.');
    }

    foreach ($datos as $campo => $valor) {
        $entrada = $_POST[$campo] ?? '';

        if (!is_string($entrada)) {
            $errores[] = 'El formulario contiene un valor inválido.';
            continue;
        }

        $datos[$campo] = trim($entrada);
    }

    if ($datos['codigo'] === '' || $datos['nombre'] === '') {
        $errores[] = 'El código y el nombre son obligatorios.';
    }

    // Se comprueba también en PHP, además de los límites del formulario.
    $limites = [
        'codigo' => 50,
        'nombre' => 150,
        'marca' => 100,
        'capacidad' => 50,
        'ubicacion' => 150,
        'descripcion' => 5000,
    ];

    foreach ($limites as $campo => $limite) {
        $longitud = preg_match_all('/./us', $datos[$campo]);

        if ($longitud === false || $longitud > $limite) {
            $errores[] = "Revisa la longitud del campo {$campo}.";
        }
    }

    foreach (['existencias', 'stock_minimo'] as $campo) {
        $numero = filter_var(
            $datos[$campo],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => 2147483647]]
        );

        if ($numero === false) {
            $errores[] = 'Las cantidades deben ser números enteros positivos o cero.';
        }
    }

    if (
        $datos['imagen'] !== '' &&
        !array_key_exists($datos['imagen'], $imagenes)
    ) {
        $errores[] = 'Selecciona una imagen válida.';
    }

    if (!$errores) {
        try {
            $consulta = $pdo->prepare(
                'INSERT INTO productos
                (
                    codigo, nombre, descripcion, marca,
                    capacidad, existencias, stock_minimo,
                    ubicacion, imagen
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $consulta->execute([
                $datos['codigo'],
                $datos['nombre'],
                $datos['descripcion'],
                $datos['marca'],
                $datos['capacidad'] ?: null,
                (int) $datos['existencias'],
                (int) $datos['stock_minimo'],
                $datos['ubicacion'] ?: null,
                $datos['imagen'] !== ''
                    ? $imagenes[$datos['imagen']]
                    : null,
            ]);

            $_SESSION['mensaje_inventario'] = 'Producto registrado correctamente.';

            header('Location: inventario.php');
            exit;
        } catch (PDOException $error) {
            if (($error->errorInfo[1] ?? null) === 1062) {
                $errores[] = 'Ese código ya está registrado. Utiliza otro.';
            } else {
                error_log($error->getMessage());
                $errores[] = 'No fue posible guardar el producto.';
            }
        }
    }
}

$mensaje = $_SESSION['mensaje_inventario'] ?? '';
unset($_SESSION['mensaje_inventario']);

$entradaBusqueda = $_GET['buscar'] ?? '';
$busqueda = is_string($entradaBusqueda)
    ? trim($entradaBusqueda)
    : '';

// Esta lista completa se conserva para calcular los contadores.
$productos = $pdo->query(
    'SELECT * FROM productos
     WHERE activo = 1
     ORDER BY creado_en DESC, id DESC'
)->fetchAll();

// Esta segunda lista contiene las tarjetas que se mostrarán.
$productosVisibles = $productos;

if ($busqueda !== '') {
    // Trata %, _ y ! como caracteres literales de la búsqueda.
    $textoBusqueda = str_replace(
        ['!', '%', '_'],
        ['!!', '!%', '!_'],
        $busqueda
    );

    $consultaBusqueda = $pdo->prepare(
        "SELECT * FROM productos
         WHERE activo = 1
           AND (
               codigo LIKE ? ESCAPE '!'
               OR nombre LIKE ? ESCAPE '!'
           )
         ORDER BY creado_en DESC, id DESC"
    );

    $patron = '%' . $textoBusqueda . '%';

    $consultaBusqueda->execute([$patron, $patron]);
    $productosVisibles = $consultaBusqueda->fetchAll();
}

$totalUnidades = 0;
$productosBajos = 0;

foreach ($productos as $producto) {
    $totalUnidades += (int) $producto['existencias'];

    if ($producto['existencias'] <= $producto['stock_minimo']) {
        $productosBajos++;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inventario | Retail Corp</title>
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

    <div class="inventario-sesion">
      <span><?= escapar($_SESSION['nombre']) ?></span>

      <form action="php/logout.php" method="POST">
        <input
          type="hidden"
          name="csrf"
          value="<?= escapar($_SESSION['csrf']) ?>"
        >
        <button class="boton-salir" type="submit">
          Cerrar sesión
        </button>
      </form>
    </div>
  </header>

  <main class="inventario-contenedor">
    <div class="inventario-titulo">
      <p class="etiqueta etiqueta-oscura">ADMINISTRACIÓN</p>
      <h1>Inventario de productos</h1>
      <p>Registra tus productos y consulta las unidades disponibles.</p>
    </div>

    <section class="resumen-inventario" aria-label="Resumen del inventario">
      <div class="resumen-tarjeta">
        <span>Productos registrados</span>
        <a href="productos-baja.php" class="enlace-bajas">
          Ver productos dados de baja
        </a>
              <form action="inventario.php" method="GET" class="buscador-productos">
                  <div class="buscador-campo">
                    <label for="buscar">Buscar por código o nombre</label>

                    <input
                      type="search"
                      id="buscar"
                      name="buscar"
                      placeholder="Ejemplo: POP-001 o recipiente"
                      value="<?= escapar($busqueda) ?>"
                    >
                  </div>

                  <button type="submit" class="boton-guardar">
                    Buscar
                  </button>

                  <a href="inventario.php" class="boton-cancelar">
                    Mostrar todos
                  </a>
            </form>

            <?php if ($busqueda !== ''): ?>
              <p class="texto-secundario" role="status">
                Resultados para “<?= escapar($busqueda) ?>”:
                <?= count($productosVisibles) ?>
              </p>
            <?php endif; ?>
        <strong><?= count($productos) ?></strong>
      </div>

      <div class="resumen-tarjeta">
        <span>Unidades disponibles</span>
        <strong><?= $totalUnidades ?></strong>
      </div>

      <div class="resumen-tarjeta">
        <span>En mínimo o por debajo</span>
        <strong><?= $productosBajos ?></strong>
      </div>
    </section>

    <?php if ($mensaje !== ''): ?>
      <p class="aviso aviso-exito" role="status">
        <?= escapar($mensaje) ?>
      </p>
    <?php endif; ?>

    <?php if ($errores): ?>
      <div class="aviso aviso-error" role="alert">
        <?php foreach (array_unique($errores) as $error): ?>
          <p><?= escapar($error) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <section class="panel-inventario">
      <h2>Registrar producto</h2>
      <p class="texto-secundario">
        Los campos con * son obligatorios.
      </p>

      <form action="inventario.php" method="POST" class="formulario-producto">
        <input
          type="hidden"
          name="csrf"
          value="<?= escapar($_SESSION['csrf']) ?>"
        >

        <div>
          <label for="codigo">Código *</label>
          <input
            id="codigo"
            name="codigo"
            maxlength="50"
            placeholder="POP-001"
            value="<?= escapar($datos['codigo']) ?>"
            required
          >
        </div>

        <div>
          <label for="nombre">Nombre *</label>
          <input
            id="nombre"
            name="nombre"
            maxlength="150"
            placeholder="Recipiente rectangular"
            value="<?= escapar($datos['nombre']) ?>"
            required
          >
        </div>

        <div>
          <label for="marca">Marca</label>
          <input
            id="marca"
            name="marca"
            maxlength="100"
            value="<?= escapar($datos['marca']) ?>"
          >
        </div>

        <div>
          <label for="capacidad">Capacidad</label>
          <input
            id="capacidad"
            name="capacidad"
            maxlength="50"
            placeholder="Déjala vacía si no la conoces"
            value="<?= escapar($datos['capacidad']) ?>"
          >
        </div>

        <div>
          <label for="existencias">Existencias *</label>
          <input
            type="number"
            id="existencias"
            name="existencias"
            min="0"
            max="2147483647"
            step="1"
            value="<?= escapar($datos['existencias']) ?>"
            required
          >
        </div>

        <div>
          <label for="stock_minimo">Stock mínimo *</label>
          <input
            type="number"
            id="stock_minimo"
            name="stock_minimo"
            min="0"
            max="2147483647"
            step="1"
            value="<?= escapar($datos['stock_minimo']) ?>"
            required
          >
        </div>

        <div>
          <label for="ubicacion">Ubicación</label>
          <input
            id="ubicacion"
            name="ubicacion"
            maxlength="150"
            placeholder="Almacén / estante"
            value="<?= escapar($datos['ubicacion']) ?>"
          >
        </div>

        <div>
          <label for="imagen">Fotografía</label>
          <select id="imagen" name="imagen">
            <option value="">Sin fotografía</option>

            <?php foreach ($imagenes as $archivo => $ruta): ?>
              <option
                value="<?= escapar($archivo) ?>"
                <?= $datos['imagen'] === $archivo ? 'selected' : '' ?>
              >
                <?= escapar($archivo) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo-completo">
          <label for="descripcion">Descripción</label>
          <textarea
            id="descripcion"
            name="descripcion"
            maxlength="5000"
            rows="3"
            placeholder="Características del producto"
          ><?= escapar($datos['descripcion']) ?></textarea>
        </div>

        <div class="campo-completo">
          <button type="submit" class="boton-guardar">
            Guardar producto
          </button>
        </div>
      </form>
    </section>

    <section class="catalogo-seccion">
      <h2>Productos registrados</h2>

      <?php if (!$productosVisibles): ?>
        <div class="inventario-vacio">
          <?php if ($busqueda !== ''): ?>
            No se encontraron productos con ese código o nombre.
          <?php else: ?>
            Todavía no hay productos. Registra el primero con el formulario.
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="catalogo-productos">
          <?php foreach ($productosVisibles as $producto): ?>
            <?php
            $stockBajo = $producto['existencias'] <= $producto['stock_minimo'];
            ?>

            <article class="producto-tarjeta">
              <div class="producto-foto">
                <?php if (in_array($producto['imagen'], $imagenes, true)): ?>
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

                <h3><?= escapar($producto['nombre']) ?></h3>

                <p class="texto-secundario">
                  <?= escapar($producto['marca']) ?>
                </p>

                <?php if ($producto['descripcion']): ?>
                  <p><?= escapar($producto['descripcion']) ?></p>
                <?php endif; ?>

                <dl class="producto-detalles">
                  <div>
                    <dt>Capacidad</dt>
                    <dd><?= escapar($producto['capacidad'] ?: 'Sin especificar') ?></dd>
                  </div>
                  <div>
                    <dt>Ubicación</dt>
                    <dd><?= escapar($producto['ubicacion'] ?: 'Sin asignar') ?></dd>
                  </div>
                  <div>
                    <dt>Stock mínimo</dt>
                    <dd><?= (int) $producto['stock_minimo'] ?></dd>
                  </div>
                </dl>

                <div class="producto-stock">
                  <strong><?= (int) $producto['existencias'] ?> unidades</strong>

                  <span class="estado-stock <?= $stockBajo ? 'stock-bajo' : '' ?>">
                    <?= $stockBajo ? 'Reponer' : 'Disponible' ?>
                  </span>
                </div>
                <a
                    class="boton-editar"
                    href="editar-producto.php?id=<?= (int) $producto['id'] ?>"
                    >
                    Editar producto
                </a>
                <a
                    class="boton-baja"
                    href="baja-producto.php?id=<?= (int) $producto['id'] ?>"
                    >
                    Dar de baja
                </a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>