const password = document.getElementById("password");
const botonMostrar = document.getElementById("mostrar-password");

botonMostrar.addEventListener("click", () => {
  const mostrar = password.type === "password";

  password.type = mostrar ? "text" : "password";
  botonMostrar.textContent = mostrar ? "Ocultar" : "Mostrar";
  botonMostrar.setAttribute("aria-pressed", String(mostrar));
});

const parametros = new URLSearchParams(window.location.search);
const error = parametros.get("error");
const mensajeError = document.getElementById("mensaje-error");

if (error && mensajeError) {
  mensajeError.textContent =
    error === "servidor"
      ? "No fue posible iniciar sesión. Intenta nuevamente."
      : "Correo o contraseña incorrectos, o cuenta inactiva.";

  mensajeError.hidden = false;
}