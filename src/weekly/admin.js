// API Configuration
const API_URL = '../api/index.php';

// --- Global Data Store ---
let weeks = [];

// --- Element Selections ---
const weekForm = document.querySelector('#week-form');
const weeksTableBody = document.querySelector('#weeks-tbody');
const weekTitleInput = document.querySelector('#week-title');
const weekStartDateInput = document.querySelector('#week-start-date');
const weekDescriptionInput = document.querySelector('#week-description');
const weekLinksInput = document.querySelector('#week-links');

// --- API Functions ---

async function fetchWeeks() {
  try {
    const response = await fetch(`${API_URL}?resource=weeks`);
    const result = await response.json();
    
    if (result.success) {
      return result.data;
    } else {
      throw new Error(result.error || 'Failed to fetch weeks');
    }
  } catch (error) {
    console.error('Error fetching weeks:', error);
    alert('Error loading weeks. Please try again.');
    return [];
  }
}

async function createWeekAPI(weekData) {
  try {
    const response = await fetch(`${API_URL}?resource=weeks`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(weekData)
    });
    
    const result = await response.json();
    
    if (result.success) {
      return result.data;
    } else {
      throw new Error(result.error || 'Failed to create week');
    }
  } catch (error) {
    console.error('Error creating week:', error);
    alert('Error creating week: ' + error.message);
    return null;
  }
}

async function updateWeekAPI(weekData) {
  try {
    const response = await fetch(`${API_URL}?resource=weeks`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(weekData)
    });
    
    const result = await response.json();
    
    if (result.success) {
      return result.data;
    } else {
      throw new Error(result.error || 'Failed to update week');
    }
  } catch (error) {
    console.error('Error updating week:', error);
    alert('Error updating week: ' + error.message);
    return null;
  }
}

async function deleteWeekAPI(weekId) {
  try {
    const response = await fetch(`${API_URL}?resource=weeks&week_id=${weekId}`, {
      method: 'DELETE'
    });
    
    const result = await response.json();
    
    if (result.success) {
      return true;
    } else {
      throw new Error(result.error || 'Failed to delete week');
    }
  } catch (error) {
    console.error('Error deleting week:', error);
    alert('Error deleting week: ' + error.message);
    return false;
  }
}

// --- Functions ---

function createWeekRow(week) {
  const tr = document.createElement('tr');
  
  const tdTitle = document.createElement('td');
  tdTitle.setAttribute('data-label', 'Week Title');
  tdTitle.textContent = week.title;
  
  const tdDescription = document.createElement('td');
  tdDescription.setAttribute('data-label', 'Description');
  tdDescription.textContent = week.description;
  
  const tdActions = document.createElement('td');
  tdActions.setAttribute('data-label', 'Actions');
  const actionsDiv = document.createElement('div');
  actionsDiv.className = 'action-buttons';
  
  const editBtn = document.createElement('button');
  editBtn.textContent = 'Edit';
  editBtn.className = 'edit-btn';
  editBtn.setAttribute('data-id', week.week_id);
  
  const deleteBtn = document.createElement('button');
  deleteBtn.textContent = 'Delete';
  deleteBtn.className = 'delete-btn';
  deleteBtn.setAttribute('data-id', week.week_id);
  
  actionsDiv.appendChild(editBtn);
  actionsDiv.appendChild(deleteBtn);
  tdActions.appendChild(actionsDiv);
  
  tr.appendChild(tdTitle);
  tr.appendChild(tdDescription);
  tr.appendChild(tdActions);
  
  return tr;
}

function renderTable() {
  weeksTableBody.innerHTML = '';
  
  if (weeks.length === 0) {
    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 3;
    td.textContent = 'No weeks added yet.';
    td.style.textAlign = 'center';
    td.style.color = '#666';
    tr.appendChild(td);
    weeksTableBody.appendChild(tr);
    return;
  }
  
  weeks.forEach(week => {
    const row = createWeekRow(week);
    weeksTableBody.appendChild(row);
  });
}

async function handleAddWeek(event) {
  event.preventDefault();
  
  const title = weekTitleInput.value.trim();
  const startDate = weekStartDateInput.value;
  const description = weekDescriptionInput.value.trim();
  const linksText = weekLinksInput.value.trim();
  
  const links = linksText
    .split('\n')
    .map(link => link.trim())
    .filter(link => link.length > 0);
  
  const weekData = {
    week_id: `week_${Date.now()}`,
    title: title,
    start_date: startDate,
    description: description,
    links: links
  };
  
  const newWeek = await createWeekAPI(weekData);
  
  if (newWeek) {
    weeks = await fetchWeeks();
    renderTable();
    weekForm.reset();
    alert('Week added successfully!');
  }
}

async function handleTableClick(event) {
  const target = event.target;
  
  if (target.classList.contains('delete-btn')) {
    const weekId = target.getAttribute('data-id');
    
    if (confirm('Are you sure you want to delete this week? All comments will also be deleted.')) {
      const success = await deleteWeekAPI(weekId);
      
      if (success) {
        weeks = await fetchWeeks();
        renderTable();
        alert('Week deleted successfully!');
      }
    }
  }
  
  if (target.classList.contains('edit-btn')) {
    const weekId = target.getAttribute('data-id');
    const week = weeks.find(w => w.week_id === weekId);
    
    if (week) {
      weekTitleInput.value = week.title;
      weekStartDateInput.value = week.start_date;
      weekDescriptionInput.value = week.description;
      weekLinksInput.value = Array.isArray(week.links) ? week.links.join('\n') : '';
      
      // Change form behavior to update
      weekForm.onsubmit = async (e) => {
        e.preventDefault();
        
        const updatedData = {
          week_id: weekId,
          title: weekTitleInput.value.trim(),
          start_date: weekStartDateInput.value,
          description: weekDescriptionInput.value.trim(),
          links: weekLinksInput.value
            .split('\n')
            .map(link => link.trim())
            .filter(link => link.length > 0)
        };
        
        const updated = await updateWeekAPI(updatedData);
        
        if (updated) {
          weeks = await fetchWeeks();
          renderTable();
          weekForm.reset();
          weekForm.onsubmit = handleAddWeek;
          alert('Week updated successfully!');
        }
      };
      
      weekForm.scrollIntoView({ behavior: 'smooth' });
    }
  }
}

async function loadAndInitialize() {
  weeks = await fetchWeeks();
  renderTable();
  
  weekForm.addEventListener('submit', handleAddWeek);
  weeksTableBody.addEventListener('click', handleTableClick);
}

// --- Initial Page Load ---
loadAndInitialize();