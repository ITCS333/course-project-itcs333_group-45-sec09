/*
    FINAL WORKING manage_users.js
    Add / Edit / Delete / Change Password
*/

const API_URL = "api/index.php";

// ----------------------
// GLOBAL STORE
// ----------------------
let students = [];

// ----------------------
// DOM ELEMENTS
// ----------------------
const studentTableBody = document.querySelector("#student-table tbody");
const addStudentForm = document.getElementById("add-student-form");
const searchInput = document.getElementById("search-input");
const passwordForm = document.getElementById("password-form");

// ----------------------
// RENDER ROW
// ----------------------
function createStudentRow(stu) {
    return `
        <tr>
            <td>${stu.name}</td>
            <td>${stu.student_id}</td>
            <td>${stu.email}</td>
            <td>
                <button class="edit-btn" data-id="${stu.student_id}">Edit</button>
                <button class="delete-btn" data-id="${stu.student_id}">Delete</button>
            </td>
        </tr>
    `;
}

function renderTable(list) {
    studentTableBody.innerHTML = list.map(createStudentRow).join("");
}

// ----------------------
// LOAD STUDENTS
// ----------------------
async function loadStudents() {
    const res = await fetch(API_URL);
    const data = await res.json();

    if (data.success) {
        students = data.data;
        renderTable(students);
    }
}

// ----------------------
// ADD STUDENT
// ----------------------
addStudentForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    const payload = {
        student_id: document.getElementById("student-id").value.trim(),
        name: document.getElementById("student-name").value.trim(),
        email: document.getElementById("student-email").value.trim(),
        password: document.getElementById("default-password").value.trim()
    };

    if (!payload.student_id || !payload.email || !payload.name) {
        alert("Please fill all fields");
        return;
    }

    const res = await fetch(API_URL, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
    });

    const json = await res.json();
    alert(json.message || (json.success ? "Student added!" : "Error"));

    if (json.success) {
        addStudentForm.reset();
        loadStudents();
    }
});

// ----------------------
// DELETE STUDENT
// ----------------------
studentTableBody.addEventListener("click", async (e) => {
    if (e.target.classList.contains("delete-btn")) {
        const id = e.target.dataset.id;

        if (!confirm("Delete this student?")) return;

        const res = await fetch(`${API_URL}?student_id=${id}`, { method: "DELETE" });
        const json = await res.json();

        alert(json.message || "Deleted");

        if (json.success) loadStudents();
    }
});

// ----------------------
// EDIT STUDENT
// ----------------------
studentTableBody.addEventListener("click", async (e) => {
    if (e.target.classList.contains("edit-btn")) {
        const id = e.target.dataset.id;
        const stu = students.find(s => s.student_id === id);

        const newName = prompt("New name:", stu.name);
        if (!newName) return;

        const newEmail = prompt("New email:", stu.email);
        if (!newEmail) return;

        const payload = {
            student_id: id,
            name: newName,
            email: newEmail
        };

        const res = await fetch(API_URL, {
            method: "PUT",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });

        const json = await res.json();
        alert(json.message || "Updated");

        if (json.success) loadStudents();
    }
});

// ----------------------
// SEARCH FILTER
// ----------------------
searchInput.addEventListener("input", () => {
    const term = searchInput.value.toLowerCase();

    const filtered = students.filter(s =>
        s.name.toLowerCase().includes(term) ||
        s.student_id.toLowerCase().includes(term) ||
        s.email.toLowerCase().includes(term)
    );

    renderTable(filtered);
});

// ----------------------
// CHANGE PASSWORD
// ----------------------
passwordForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    const studentId = document.getElementById("student-id-password").value.trim();
    const currentPassword = document.getElementById("current-password").value.trim();
    const newPassword = document.getElementById("new-password").value.trim();
    const confirmPassword = document.getElementById("confirm-password").value.trim();

    if (newPassword !== confirmPassword) {
        alert("New password and Confirm password do NOT match!");
        return;
    }

    const payload = {
        student_id: studentId,
        current_password: currentPassword,
        new_password: newPassword
    };

    const res = await fetch("api/index.php?action=change_password", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
    });

    const json = await res.json();
    alert(json.message);

    if (json.success) passwordForm.reset();
});

// ----------------------
// INIT
// ----------------------
loadStudents();
