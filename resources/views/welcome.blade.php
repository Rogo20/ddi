<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @vite(['resources/sass/app.scss', 'resources/js/app.js'])

        <style>
            .drug-tag {
                display: inline-flex;
                align-items: center;
                gap: 0.25rem;
                padding: 0.25rem 0.5rem;
                border-radius: 0.25rem;
                font-size: 0.875rem;
            }
            .drug-tag .btn-close {
                font-size: 0.6rem;
                padding: 0.15rem;
            }
            .dropdown-menu {
                max-height: 250px;
                overflow-y: auto;
            }
        </style>
    </head>
    <body class="bg-light d-flex flex-column min-vh-100">

        <div class="container-fluid p-5 bg-primary text-white text-center">
            <h1>DDI</h1>
            <p>Drug-Drug Interaction Checker</p>
        </div>

        <main class="flex-grow-1 d-flex align-items-center justify-content-center py-4">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="card shadow-sm">
                            <div class="card-body p-4">
                                <h5 class="card-title mb-3">Medikamente auswählen</h5>

                                <div class="position-relative mb-3">
                                    <input
                                        type="text"
                                        id="drugSearch"
                                        class="form-control"
                                        placeholder="Medikament suchen (z.B. aspirin, ibuprofen)..."
                                        autocomplete="off"
                                    >
                                    <ul id="drugDropdown" class="dropdown-menu w-100" style="display: none;"></ul>
                                    <div id="searchSpinner" class="position-absolute top-50 end-0 translate-middle-y me-3" style="display: none;">
                                        <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
                                    </div>
                                </div>

                                <div id="selectedDrugs" class="d-flex flex-wrap gap-2 mb-4"></div>

                                <button id="checkBtn" class="btn btn-primary" disabled>
                                    <span id="checkBtnText">Wechselwirkungen prüfen</span>
                                    <span id="checkBtnSpinner" class="spinner-border spinner-border-sm ms-1" style="display: none;" role="status"></span>
                                </button>

                                <div id="results" class="mt-4" style="display: none;"></div>

                                <div class="mt-3">
                                    <small class="text-muted">
                                        Daten bereitgestellt von <a href="https://open.fda.gov/" target="_blank" rel="noopener">openFDA</a>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <script>
            const FDA_BASE = 'https://api.fda.gov/drug/label.json';
            let selectedDrugs = [];
            let searchTimeout = null;

            const searchInput = document.getElementById('drugSearch');
            const dropdown = document.getElementById('drugDropdown');
            const searchSpinner = document.getElementById('searchSpinner');
            const selectedContainer = document.getElementById('selectedDrugs');
            const checkBtn = document.getElementById('checkBtn');
            const checkBtnText = document.getElementById('checkBtnText');
            const checkBtnSpinner = document.getElementById('checkBtnSpinner');
            const resultsDiv = document.getElementById('results');

            searchInput.addEventListener('input', () => {
                const query = searchInput.value.trim();
                clearTimeout(searchTimeout);

                if (query.length < 2) {
                    dropdown.style.display = 'none';
                    return;
                }

                searchTimeout = setTimeout(() => searchDrugs(query), 400);
            });

            async function searchDrugs(query) {
                searchSpinner.style.display = 'block';
                dropdown.style.display = 'none';

                try {
                    const url = `${FDA_BASE}?search=openfda.generic_name:${encodeURIComponent(query)}*&count=openfda.generic_name.exact&limit=15`;
                    const res = await fetch(url);
                    const data = await res.json();

                    const drugs = (data.results || [])
                        .map(r => r.term)
                        .filter(name => {
                            const lower = name.toLowerCase();
                            return lower.includes(query.toLowerCase())
                                && !selectedDrugs.includes(name);
                        });

                    dropdown.innerHTML = '';

                    if (drugs.length === 0) {
                        const li = document.createElement('li');
                        li.innerHTML = '<span class="dropdown-item text-muted">Keine Ergebnisse</span>';
                        dropdown.appendChild(li);
                    } else {
                        drugs.forEach(drug => {
                            const li = document.createElement('li');
                            const a = document.createElement('a');
                            a.className = 'dropdown-item';
                            a.href = '#';
                            a.textContent = drug.toLowerCase();
                            a.addEventListener('click', e => {
                                e.preventDefault();
                                addDrug(drug);
                            });
                            li.appendChild(a);
                            dropdown.appendChild(li);
                        });
                    }

                    dropdown.style.display = 'block';
                } catch {
                    dropdown.innerHTML = '<li><span class="dropdown-item text-danger">Fehler bei der Suche</span></li>';
                    dropdown.style.display = 'block';
                } finally {
                    searchSpinner.style.display = 'none';
                }
            }

            document.addEventListener('click', e => {
                if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.style.display = 'none';
                }
            });

            function addDrug(drug) {
                if (selectedDrugs.includes(drug)) return;
                selectedDrugs.push(drug);
                renderSelected();
                searchInput.value = '';
                dropdown.style.display = 'none';
                checkBtn.disabled = selectedDrugs.length < 2;
            }

            function removeDrug(drug) {
                selectedDrugs = selectedDrugs.filter(d => d !== drug);
                renderSelected();
                checkBtn.disabled = selectedDrugs.length < 2;
                resultsDiv.style.display = 'none';
            }

            function renderSelected() {
                selectedContainer.innerHTML = '';
                selectedDrugs.forEach(drug => {
                    const tag = document.createElement('span');
                    tag.className = 'drug-tag bg-primary bg-opacity-10 text-primary';
                    tag.innerHTML = `${drug.toLowerCase()} <button type="button" class="btn-close" aria-label="Remove"></button>`;
                    tag.querySelector('.btn-close').addEventListener('click', () => removeDrug(drug));
                    selectedContainer.appendChild(tag);
                });
            }

            function extractRelevantText(fullText, drugName) {
                const sentences = fullText.split(/(?<=[.!?])\s+/);
                const drugWords = drugName.toLowerCase().split(/[,\s]+/).filter(w => w.length > 3);
                const relevant = [];

                for (const sentence of sentences) {
                    const lower = sentence.toLowerCase();
                    if (drugWords.some(w => lower.includes(w))) {
                        relevant.push(sentence.trim());
                    }
                }

                return relevant.join(' ').substring(0, 500) || null;
            }

            checkBtn.addEventListener('click', async () => {
                if (selectedDrugs.length < 2) return;

                checkBtn.disabled = true;
                checkBtnSpinner.style.display = 'inline-block';
                checkBtnText.textContent = 'Prüfe...';
                resultsDiv.style.display = 'none';

                const found = [];

                try {
                    for (let i = 0; i < selectedDrugs.length; i++) {
                        const drugA = selectedDrugs[i];
                        const primaryName = drugA.split(/[,\s]+/)[0];

                        const url = `${FDA_BASE}?search=openfda.generic_name:"${encodeURIComponent(primaryName)}"+AND+_exists_:drug_interactions&limit=3`;
                        const res = await fetch(url);

                        if (!res.ok) continue;

                        const data = await res.json();
                        const results = data.results || [];

                        for (const label of results) {
                            const interactionText = (label.drug_interactions || []).join(' ');
                            if (!interactionText) continue;

                            for (let j = 0; j < selectedDrugs.length; j++) {
                                if (i === j) continue;
                                const drugB = selectedDrugs[j];
                                const drugBWords = drugB.toLowerCase().split(/[,\s]+/).filter(w => w.length > 3);
                                const textLower = interactionText.toLowerCase();

                                if (drugBWords.some(w => textLower.includes(w))) {
                                    const key = [drugA, drugB].sort().join('|||');
                                    if (!found.some(f => f.key === key)) {
                                        const excerpt = extractRelevantText(interactionText, drugB);
                                        found.push({
                                            key,
                                            drugA: drugA.toLowerCase(),
                                            drugB: drugB.toLowerCase(),
                                            description: excerpt || 'Wechselwirkung in FDA-Label dokumentiert.'
                                        });
                                    }
                                }
                            }
                        }
                    }

                    resultsDiv.style.display = 'block';

                    if (found.length === 0) {
                        resultsDiv.innerHTML = `
                            <div class="alert alert-success">
                                <strong>Keine Wechselwirkungen gefunden</strong> zwischen den ausgewählten Medikamenten.
                            </div>`;
                    } else {
                        let html = `
                            <div class="alert alert-warning">
                                <strong>${found.length} Wechselwirkung(en) gefunden:</strong>
                            </div>
                            <ul class="list-group">`;

                        found.forEach(inter => {
                            html += `
                                <li class="list-group-item">
                                    <strong>${inter.drugA}</strong> &harr; <strong>${inter.drugB}</strong>
                                    <br><small class="text-muted">${inter.description}</small>
                                </li>`;
                        });

                        html += '</ul>';
                        resultsDiv.innerHTML = html;
                    }
                } catch (err) {
                    resultsDiv.style.display = 'block';
                    resultsDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <strong>Fehler</strong> beim Abrufen der Wechselwirkungen. Bitte versuche es erneut.
                        </div>`;
                } finally {
                    checkBtn.disabled = selectedDrugs.length < 2;
                    checkBtnSpinner.style.display = 'none';
                    checkBtnText.textContent = 'Wechselwirkungen prüfen';
                }
            });
        </script>
    </body>
</html>
