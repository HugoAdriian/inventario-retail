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
    'SELECT * FROM productos WHERE id = ? AND activo = 1'
);
$consulta->execute([$id]);
$producto = $consulta->fetch();

if (!$producto) {
    http_response_code(404);
    exit('No se encontró el producto.');
}

$imagenes = [];

for ($numero = 1; $numero <= 11; $numero++) {
    $archivo = sprintf('producto-%02d.jpeg', $numero);
    $imagenes[$archivo] = 'img/productos/' . $archivo;
}

$imagenSeleccionada = array_search(
    $producto['imagen'],
    $imagenes,
    true
);

$datos = [
    'codigo' => $producto['codigo'],
    'nombre' => $producto['nombre'],
    'descripcion' => $producto['descripcion'] ?? '',
    'marca' => $producto['marca'],
    'capacidad' => $producto['capacidad'] ?? '',
    'existencias' => (string) $producto['existencias'],
    'stock_minimo' => (string) $producto['stock_minimo'],
    'ubicacion' => $producto['ubicacion'] ?? '',
    'imagen' => $imagenSeleccionada !== false ? $imagenSeleccionada : '',
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
                'UPDATE productos
                 SET codigo = ?,
                     nombre = ?,
                     descripcion = ?,
                     marca = ?,
                     capacidad = ?,
                     existencias = ?,
                     stock_minimo = ?,
                     ubicacion = ?,
                     imagen = ?
                 WHERE id = ? AND activo = 1'
            );

            $consulta->execute([
                $datos['codigo'],
                $datos['nombre'],
                $datos['descripcion'],
                $datos['marca'],
                $datos['capacidad'] !== '' ? $datos['capacidad'] : null,
                (int) $datos['existencias'],
                (int) $datos['stock_minimo'],
                $datos['ubicacion'] !== '' ? $datos['ubicacion'] : null,
                $datos['imagen'] !== ''
                    ? $imagenes[$datos['imagen']]
                    : null,
                $id,
            ]);

            $_SESSION['mensaje_inventario'] = 'Producto actualizado correctamente.';

            header('Location: inventario.php');
            exit;
        } catch (PDOException $error) {
            if (($error->errorInfo[1] ?? null) === 1062) {
                $errores[] = 'Ese código pertenece a otro producto.';
            } else {
                error_log($error->getMessage());
                $errores[] = 'No fue posible actualizar el producto.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Editar producto | Retail Corp</title>
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

    <a class="boton-salir" href="inventario.php">
      Volver al inventario
    </a>
  </header>

  <main class="inventario-contenedor">
    <div class="inventario-titulo">
      <p class="etiqueta etiqueta-oscura">ADMINISTRACIÓN</p>
      <h1>Editar producto</h1>
      <p>Actualiza los datos y guarda tus cambios.</p>
    </div>

    <?php if ($errores): ?>
      <div class="aviso aviso-error" role="alert">
        <?php foreach (array_unique($errores) as $error): ?>
          <p><?= escapar($error) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <section class="panel-inventario">
      <h2>Datos del producto</h2>

      <form
        action="editar-producto.php?id=<?= (int) $id ?>"
        method="POST"
        class="formulario-producto"
      >
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
          ><?= escapar($datos['descripcion']) ?></textarea>
        </div>

        <div class="campo-completo acciones-edicion">
          <button type="submit" class="boton-guardar">
            Guardar cambios
          </button>

          <a href="inventario.php" class="boton-cancelar">
            Cancelar
          </a>
        </div>
      </form>
    </section>
  </main>
</body>
</html>