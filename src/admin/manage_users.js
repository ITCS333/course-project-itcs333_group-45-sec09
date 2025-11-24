/*
  Requirement: Add interactivity and data management to the Admin Portal.

  Instructions:
  1. Link this file to your HTML using a <script> tag with the 'defer' attribute.
  2. Implement the JavaScript functionality as described in the TODO comments.
  3. All data management will be done by manipulating the 'students' array
     and re-rendering the table.
*/

// --- Global Data Store ---
// This array will be populated with data fetched from 'students.json'.
let students = [];

// --- Element Selections ---
// TODO: Select the student table body (tbody).
const studentTableBody = document.querySelector("#student-table tbody");

// TODO: Select the "Add Student" form.
// (Requires id="add-student-form" in HTML)
const addStudentForm = document.getElementById("add-student-form");

// TODO: Select the "Change Password" form.
const changePasswordForm = document.getElementById("password-form");

// TODO: Select the search input field.
const searchInput = document.getElementById("search-input");

// TODO: Select all table header (th) elements in thead.
const tableHeaders = document.querySelectorAll("thead th");

// --- Functions ---

/** 1
 * TODO: Implement the createStudentRow function.
 * This function should take a student object {name, id, email} and return a <tr> element.
 */
function createStudentRow(student) {
  const tr = document.createElement("tr");

  const nameTd = document.createElement("td");
  nameTd.textContent = student.name;

  const idTd = document.createElement("td");
  idTd.textContent = student.id;

  const emailTd = document.createElement("td");
  emailTd.textContent = student.email;

  const actionsTd = document.createElement("td");

  const editBtn = document.createElement("button");
  editBtn.textContent = "Edit";
  editBtn.classList.add("edit-btn");
  editBtn.dataset.id = student.id;

  const deleteBtn = document.createElement("button");
  deleteBtn.textContent = "Delete";
  deleteBtn.classList.add("delete-btn");
  deleteBtn.dataset.id = student.id;

  actionsTd.appendChild(editBtn);
  actionsTd.appendChild(deleteBtn);

  tr.appendChild(nameTd);
  tr.appendChild(idTd);
  tr.appendChild(emailTd);
  tr.appendChild(actionsTd);

  return tr;
}

/** 2
 * TODO: Implement the renderTable function.
 * Should clear table body and append rows.
 */
function renderTable(studentArray) {
  studentTableBody.innerHTML = "";

  studentArray.forEach((student) => {
    const row = createStudentRow(student);
    studentTableBody.appendChild(row);
  });
}

/** 3
 * TODO: Implement the handleChangePassword function.
 * Validate and update password.
 */
function handleChangePassword(event) {
  event.preventDefault();

  const currentInput = document.getElementById("current-password");
  const newInput = document.getElementById("new-password");
  const confirmInput = document.getElementById("confirm-password");

  const currentVal = currentInput.value;
  const newVal = newInput.value;
  const confirmVal = confirmInput.value;

  if (newVal !== confirmVal) {
    alert("Passwords do not match.");
    return;
  }

  if (newVal.length < 8) {
    alert("Password must be at least 8 characters.");
    return;
  }

  alert("Password updated successfully!");

  currentInput.value = "";
  newInput.value = "";
  confirmInput.value = "";
}

/**
 * TODO: Implement the handleAddStudent function.
 * Add student after validation.
 */
function handleAddStudent(event) {
  event.preventDefault();

  const nameInput = document.getElementById("student-name");
  const idInput = document.getElementById("student-id");
  const emailInput = document.getElementById("student-email");
  const defaultPasswordInput = document.getElementById("default-password");

  const name = nameInput.value.trim();
  const id = idInput.value.trim();
  const email = emailInput.value.trim();

  if (!name || !id || !email) {
    alert("Please fill out all required fields.");
    return;
  }

  const exists = students.some((s) => s.id === id);
  if (exists) {
    alert("A student with this ID already exists.");
    return;
  }

  const newStudent = { name, id, email };
  students.push(newStudent);

  renderTable(students);

  nameInput.value = "";
  idInput.value = "";
  emailInput.value = "";
  defaultPasswordInput.value = "password123";
}

/**
 * TODO: Implement the handleTableClick function.
 * Handles Delete (and optional Edit).
 */
function handleTableClick(event) {
  const target = event.target;

  if (target.classList.contains("delete-btn")) {
    const id = target.dataset.id;

    students = students.filter((s) => s.id !== id);

    renderTable(students);
  }

  if (target.classList.contains("edit-btn")) {
    const id = target.dataset.id;
    const student = students.find((s) => s.id === id);

    if (!student) return;

    const newName = prompt("Enter new name:", student.name);
    if (!newName) return;

    const newEmail = prompt("Enter new email:", student.email);
    if (!newEmail) return;

    student.name = newName;
    student.email = newEmail;

    renderTable(students);
  }
}

/**
 * TODO: Implement the handleSearch function.
 * Filters students by name.
 */
function handleSearch(event) {
  const term = searchInput.value.toLowerCase().trim();

  if (term === "") {
    renderTable(students);
    return;
  }

  const filtered = students.filter((s) =>
    s.name.toLowerCase().includes(term)
  );

  renderTable(filtered);
}

/**
 * TODO: Implement the handleSort function.
 * Sort table by name/id/email.
 */
function handleSort(event) {
  const th = event.currentTarget;
  const index = th.cellIndex;

  let key;
  if (index === 0) key = "name";
  else if (index === 1) key = "id";
  else if (index === 2) key = "email";
  else return;

  let direction = th.dataset.sortDir || "asc";
  direction = direction === "asc" ? "desc" : "asc";
  th.dataset.sortDir = direction;

  students.sort((a, b) => {
    let comp = 0;

    if (key === "id") {
      comp = a.id.localeCompare(b.id);
    } else {
      comp = a[key].localeCompare(b[key]);
    }

    return direction === "asc" ? comp : -comp;
  });

  renderTable(students);
}

/**
 * TODO: Implement the loadStudentsAndInitialize function.
 * Load JSON → populate table → bind all listeners.
 */
async function loadStudentsAndInitialize() {
  try {
    const response = await fetch("students.json");

    if (response.ok) {
      const data = await response.json();
      if (Array.isArray(data)) {
        students = data;
      }
    }
  } catch (err) {
    console.error("Error fetching students.json:", err);
  }

  // Fallback: read existing rows if JSON not loaded
  if (students.length === 0) {
    const rows = studentTableBody.querySelectorAll("tr");
    students = Array.from(rows).map((row) => {
      const cells = row.querySelectorAll("td");
      return {
        name: cells[0]?.textContent.trim() || "",
        id: cells[1]?.textContent.trim() || "",
        email: cells[2]?.textContent.trim() || "",
      };
    });
  }

  renderTable(students);

  changePasswordForm.addEventListener("submit", handleChangePassword);
  addStudentForm.addEventListener("submit", handleAddStudent);
  studentTableBody.addEventListener("click", handleTableClick);
  searchInput.addEventListener("input", handleSearch);
  tableHeaders.forEach((th) => th.addEventListener("click", handleSort));
}

// --- Initial Page Load ---
loadStudentsAndInitialize();
