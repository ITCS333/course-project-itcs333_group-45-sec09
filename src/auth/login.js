/*
    FINAL LOGIN.JS (WORKING WITH YOUR PROJECT STRUCTURE)
*/

const API_URL = "./api/index.php";

const loginForm = document.getElementById("login-form");
const emailInput = document.getElementById("email");
const passwordInput = document.getElementById("password");
const messageContainer = document.getElementById("message-container");

function displayMessage(msg, type) {
    messageContainer.textContent = msg;
    messageContainer.className = type;
}

function isValidEmail(email) {
  return /\S+@\S+\.\S+/.test(email);
}

function isValidPassword(password) {
  return typeof password === "string" && password.length >= 8;
}

function handleLogin(event) {
  event.preventDefault();

  const email = emailInput.value.trim();
  const password = passwordInput.value.trim();

  if (!isValidEmail(email)) {
    displayMessage("Invalid email format.", "error");
    return;
  }

  if (!isValidPassword(password)) {
    displayMessage("Password must be at least 8 characters.", "error");
    return;
  }

  displayMessage("Login successful!", "success");
  emailInput.value = "";
  passwordInput.value = "";
}


loginForm.addEventListener("submit", async function (e) {
    e.preventDefault();

    const email = emailInput.value.trim();
    const password = passwordInput.value.trim();

    if (!/\S+@\S+\.\S+/.test(email)) {
        displayMessage("Invalid email format.", "error");
        return;
    }

    if (password.length < 8) {
        displayMessage("Password must be at least 8 characters.", "error");
        return;
    }

    try {
        const res = await fetch(API_URL, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ email, password })
        });

        const result = await res.json();
        console.log("API RESPONSE:", result);

        if (result.success) {
            displayMessage("Login successful! Redirecting...", "success");
            setTimeout(() => {
                window.location.href = "../../index.html";
            }, 1000);
        } else {
            displayMessage(result.message, "error");
        }

    } catch (err) {
        console.error(err);
        displayMessage("Network error. Try again.", "error");
    }
});
