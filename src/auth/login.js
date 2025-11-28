/*
    LOGIN SYSTEM (Connected to MySQL PHP API)
    - Validates email & password on client
    - Sends JSON to PHP API
    - Shows correct success/error message
    - Redirects to admin panel on success
*/

// Use a RELATIVE URL so it always points to the correct place:
// login/auth/login.html -> calls login/auth/api/index.php
const API_URL = "api/index.php";

// --- Element Selections ---
const loginForm = document.getElementById("login-form");
const emailInput = document.getElementById("email");
const passwordInput = document.getElementById("password");
const messageContainer = document.getElementById("message-container");

// --- Helpers ---
function displayMessage(message, type) {
  if (!messageContainer) return;
  messageContainer.textContent = message;
  messageContainer.className = type; // e.g. "success" or "error"
}

function isValidEmail(email) {
  return /\S+@\S+\.\S+/.test(email);
}

function isValidPassword(password) {
  return password.length >= 8;
}

// --- Main handler ---
async function handleLogin(event) {
  event.preventDefault(); // Stop HTML form from actually submitting

  const emailValue = emailInput.value.trim();
  const passwordValue = passwordInput.value.trim();

  // 1) Client-side validation
  if (!isValidEmail(emailValue)) {
    displayMessage("Invalid email format.", "error");
    return;
  }

  if (!isValidPassword(passwordValue)) {
    displayMessage("Password must be at least 8 characters.", "error");
    return;
  }

  // 2) Call the PHP API
  try {
    const response = await fetch(API_URL, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        email: emailValue,
        password: passwordValue,
      }),
    });

    // If PHP returns HTML because of an error, this can throw:
    let result;
    try {
      result = await response.json();
    } catch (parseErr) {
      console.error("Response is not valid JSON:", parseErr);
      displayMessage("Server returned invalid response.", "error");
      return;
    }

    console.log("Login response from API:", result);

    if (result.success) {
      displayMessage("Login successful! Redirecting...", "success");

      // Redirect to admin panel
      setTimeout(() => {
        // from auth/login.html to admin/manage_users.html
        window.location.href = "../admin/manage_users.html";
      }, 1000);
    } else {
      // Backend says login failed
      displayMessage(result.message || "Invalid email or password.", "error");
    }
  } catch (error) {
    console.error("Login error (network or JS):", error);
    displayMessage("Server error. Please try again later.", "error");
  }
}

// --- Setup ---
function setupLoginForm() {
  if (!loginForm) {
    console.error("loginForm not found. Check id='login-form' in login.html");
    return;
  }
  loginForm.addEventListener("submit", handleLogin);
}

setupLoginForm();
