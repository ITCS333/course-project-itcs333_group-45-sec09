/*
    Updated manage_users.js
    Fully connected to MySQL REST API
*/

const API_URL = "../admin/api/index.php";

// --- Global Data Store ---
let students = [];

// Element Selections
const studentTableBody = document.querySelector("#student-table tbody");
const addStudentForm = document.getElementById("add-student-form");
const changePasswordForm = document.getElementById("password-form");
const searchInput = document.getElementById("search-input");
const tableHeaders = document.querySelectorAll("thead th");

// ---------------------------------------------------------
// HELPER: Create Table Row
// ---------------------------------------------------------
function createStudentRow(student) {
    const tr = document.createElement("tr");

    tr.innerHTML = `
        <td>${student.name}</td>
        <td>${student.student_id}</td>
        <td>${student.email}</td>
        <td>
            <button class="edit-btn" data-id="${student.student_id}">Edit</button>
            <button class="delete-btn" data-id="${student.student_id}">Delete</button>
        </td>
    `;

    return tr;
}

// ---------------------------------------------------------
// Render Table
// ---------------------------------------------------------
function renderTable(studentArray) {
    studentTableBody.innerHTML = "";
    studentArray.forEach(student => {
        studentTableBody.appendChild(createStudentRow(student));
    });
}

// ---------------------------------------------------------
// LOAD STUDENTS FROM DATABASE
// ---------------------------------------------------------
async function loadStudents() {
    try {
        const res = await fetch(API_URL);
        const data = await res.json();

        if (data.success) {
            students = data.data;
            renderTable(students);
        }
    } catch (err) {
        console.error("Error loading students:", err);
    }
}

// ---------------------------------------------------------
// ADD STUDENT
// ---------------------------------------------------------
async function handleAddStudent(event) {
    event.preventDefault();

    const payload = {
        student_id: document.getElementById("student-id").value.trim(),
        name: document.getElementById("student-name").value.trim(),
        email: document.getElementById("student-email").value.trim(),
        password: document.getElementById("default-password").value.trim()
    };

    if (!payload.student_id || !payload.name || !payload.email) {
        alert("Please fill out all required fields.");
        return;
    }

    try {
        const res = await fetch(API_URL, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });

        const result = await res.json();

        if (result.success) {
            alert("Student added successfully");
            await loadStudents();
            addStudentForm.reset();
            document.getElementById("default-password").value = "password123";
        } else {
            alert(result.message);
        }
    } catch (err) {
        console.error("Error adding student:", err);
    }
}

// ---------------------------------------------------------
// DELETE STUDENT
// ---------------------------------------------------------
async function deleteStudent(studentId) {
    if (!confirm("Are you sure you want to delete this student?")) return;

    try {
        const res = await fetch(`${API_URL}?student_id=${studentId}`, {
            method: "DELETE"
        });

        const result = await res.json();

        if (result.success) {
            alert("Student deleted");
            await loadStudents();
        } else {
            alert(result.message);
        }
    } catch (err) {
        console.error("Error deleting student:", err);
    }
}

// ---------------------------------------------------------
// EDIT STUDENT
// ---------------------------------------------------------
async function editStudent(studentId) {
    const student = students.find(s => s.student_id === studentId);
    if (!student) return;

    const newName = prompt("New name:", student.name);
    if (!newName) return;

    const newEmail = prompt("New email:", student.email);
    if (!newEmail) return;

    try {
        const res = await fetch(API_URL, {
            method: "PUT",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                student_id: studentId,
                name: newName,
                email: newEmail
            })
        });

        const result = await res.json();

        if (result.success) {
            alert("Student updated");
            await loadStudents();
        } else {
            alert(result.message);
        }

    } catch (err) {
        console.error("Error editing student:", err);
    }
}

// ---------------------------------------------------------
// TABLE CLICK HANDLER
// ---------------------------------------------------------
function handleTableClick(event) {
    const btn = event.target;
    if (btn.classList.contains("delete-btn")) {
        deleteStudent(btn.dataset.id);
    }
    if (btn.classList.contains("edit-btn")) {
        editStudent(btn.dataset.id);
    }
}

// ---------------------------------------------------------
// SEARCH STUDENTS
// ---------------------------------------------------------
function handleSearch() {
    const term = searchInput.value.toLowerCase();
    const filtered = students.filter(s =>
        s.name.toLowerCase().includes(term)
    );
    renderTable(filtered);
}

// ---------------------------------------------------------
// SORTING
// ---------------------------------------------------------
function handleSort(event) {
    const th = event.currentTarget;
    const index = th.cellIndex;

    let key = ["name", "student_id", "email"][index];
    let direction = th.dataset.sortDir === "asc" ? "desc" : "asc";
    th.dataset.sortDir = direction;

    students.sort((a, b) => {
        let comp = a[key].localeCompare(b[key]);
        return direction === "asc" ? comp : -comp;
    });

    renderTable(students);
}

// ---------------------------------------------------------
// INITIALIZE
// ---------------------------------------------------------
async function init() {
    await loadStudents();
    addStudentForm.addEventListener("submit", handleAddStudent);
    searchInput.addEventListener("input", handleSearch);
    studentTableBody.addEventListener("click", handleTableClick);
    tableHeaders.forEach(th => th.addEventListener("click", handleSort));
}

init();
