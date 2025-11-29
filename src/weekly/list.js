// API Configuration
const API_URL = '../api/index.php';

// --- Element Selections ---
const listSection = document.querySelector('#week-list-section');

// --- Functions ---

function createWeekArticle(week) {
  const article = document.createElement('article');
  
  const h2 = document.createElement('h2');
  h2.textContent = week.title;
  
  const datePara = document.createElement('p');
  datePara.className = 'week-date';
  datePara.textContent = `Starts on: ${week.start_date}`;
  
  const descPara = document.createElement('p');
  descPara.textContent = week.description;
  
  const link = document.createElement('a');
  link.href = `details.html?id=${week.week_id}`;
  link.className = 'view-details-link';
  link.textContent = 'View Details & Discussion →';
  
  article.appendChild(h2);
  article.appendChild(datePara);
  article.appendChild(descPara);
  article.appendChild(link);
  
  return article;
}

async function loadWeeks() {
  try {
    const response = await fetch(`${API_URL}?resource=weeks`);
    const result = await response.json();
    
    if (!result.success) {
      throw new Error(result.error || 'Failed to load weeks');
    }
    
    const weeks = result.data;
    
    listSection.innerHTML = '';
    
    if (weeks.length === 0) {
      const emptyMessage = document.createElement('p');
      emptyMessage.textContent = 'No weeks have been added yet.';
      emptyMessage.style.textAlign = 'center';
      emptyMessage.style.color = '#666';
      listSection.appendChild(emptyMessage);
      return;
    }
    
    weeks.forEach(week => {
      const article = createWeekArticle(week);
      listSection.appendChild(article);
    });
    
    console.log('Weeks loaded successfully:', weeks);
    
  } catch (error) {
    console.error('Error loading weeks:', error);
    
    listSection.innerHTML = '';
    const errorMessage = document.createElement('p');
    errorMessage.textContent = 'Error loading weekly breakdown. Please try again later.';
    errorMessage.style.color = '#dc3545';
    errorMessage.style.textAlign = 'center';
    listSection.appendChild(errorMessage);
  }
}

// --- Initial Page Load ---
loadWeeks();