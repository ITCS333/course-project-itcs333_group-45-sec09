let resources = [];

 
const resourceForm = document.querySelector("#resource-form");
const resourcesTableBody = document.querySelector("#resources-tbody");

// Functions 

/**
 
 * @param {Object} resource 
 */
function createResourceRow(resource) {
  const { id, title, description } = resource;

  const tr = document.createElement("tr");

  const tdTitle = document.createElement("td");
  tdTitle.textContent = title;

  const tdDescription = document.createElement("td");
  tdDescription.textContent = description;

  const tdActions = document.createElement("td");

  // Edit Button
  const editBtn = document.createElement("button");
  editBtn.classList.add("edit-btn");
  editBtn.dataset.id = id;
  editBtn.textContent = "Edit";

  // Delete Button
  const deleteBtn = document.createElement("button");
  deleteBtn.classList.add("delete-btn");
  deleteBtn.dataset.id = id;
  deleteBtn.textContent = "Delete";

  tdActions.appendChild(editBtn);
  tdActions.appendChild(deleteBtn);

  tr.appendChild(tdTitle);
  tr.appendChild(tdDescription);
  tr.appendChild(tdActions);

  return tr;
}


function renderTable() {
  resourcesTableBody.innerHTML = ""; 

  resources.forEach(resource => {
    const row = createResourceRow(resource);
    resourcesTableBody.appendChild(row);
    
  });
}


function handleAddResource(event) {
  event.preventDefault();

 
  const titleInput = document.querySelector("#resource-title");
  const descInput = document.querySelector("#resource-description");
  const linkInput = document.querySelector("#resource-link");

  const newResource = {
    id: `res_${Date.now()}`,
    title: titleInput.value.trim(),
    description: descInput.value.trim(),
    link: linkInput.value.trim(),
  };

  resources.push(newResource);

  renderTable();
  resourceForm.reset();
}


function handleTableClick(event) {
  if (event.target.classList.contains("delete-btn")) {
    const id = event.target.dataset.id;

    resources = resources.filter(r => r.id !== id);

    renderTable();
  }
}


async function loadAndInitialize() {
  try {
    const res = await fetch("resources.json");
    resources = await res.json();
  } catch (error) {
    console.warn("⚠️ Warning: Could not load resources.json", error);
    resources = [];
  }

  renderTable();
  resourceForm.addEventListener("submit", handleAddResource);
  resourcesTableBody.addEventListener("click", handleTableClick);
}


loadAndInitialize();
