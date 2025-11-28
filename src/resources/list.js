/*
  Requirement: Populate the "Course Resources" list page.

  Instructions:
  1. Link this file to `list.html` using:
     <script src="list.js" defer></script>

  2. In `list.html`, add an `id="resource-list-section"` to the
     <section> element that will contain the resource articles.

  3. Implement the TODOs below.
*/

const listSection = document.getElementById('resource-list-section');

function createResourceArticle(resource) {
  const article = document.createElement('article');

  const h2 = document.createElement('h2');
  h2.textContent = resource.title;

  const p = document.createElement('p');
  p.textContent = resource.description;

  const a = document.createElement('a');
  a.textContent = "View Resource & Discussion";
  a.href = `details.html?id=${resource.id}`;

  article.appendChild(h2);
  article.appendChild(p);
  article.appendChild(a);

  return article;
}

async function loadResources() {
  try {
    const response = await fetch('resources.json');
    const resources = await response.json();
    listSection.innerHTML = '';
    resources.forEach(resource => {
      const article = createResourceArticle(resource);
      listSection.appendChild(article);
    });
  } catch (error) {
    console.error("Failed to load resources:", error);
    listSection.textContent = "Failed to load resources.";
  }
}

loadResources();
