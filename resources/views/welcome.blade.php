<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <meta name="csrf-token" content="{{ csrf_token() }}">

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
            #drugDropdown {
                max-height: 250px;
                overflow-y: auto;
                display: none;
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
                                        placeholder="Medikament suchen..."
                                        autocomplete="off"
                                    >
                                    <ul id="drugDropdown" class="dropdown-menu w-100"></ul>
                                </div>

                                <div id="selectedDrugs" class="d-flex flex-wrap gap-2 mb-4"></div>

                                <button id="checkBtn" class="btn btn-primary" disabled>
                                    <span id="checkBtnText">Wechselwirkungen prüfen</span>
                                    <span id="checkBtnSpinner" class="spinner-border spinner-border-sm ms-1" style="display: none;" role="status"></span>
                                </button>

                                <div id="results" class="mt-4" style="display: none;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <script>
            let drugNames = [];
            let selectedDrugs = [];

            fetch('/data/drug_names.json')
                .then(r => r.json())
                .then(names => { drugNames = names; });

            const searchInput = document.getElementById('drugSearch');
            const dropdown = document.getElementById('drugDropdown');
            const selectedContainer = document.getElementById('selectedDrugs');
            const checkBtn = document.getElementById('checkBtn');
            const checkBtnText = document.getElementById('checkBtnText');
            const checkBtnSpinner = document.getElementById('checkBtnSpinner');
            const resultsDiv = document.getElementById('results');

            searchInput.addEventListener('input', () => {
                const query = searchInput.value.trim().toLowerCase();
                dropdown.innerHTML = '';

                if (query.length < 1) {
                    dropdown.style.display = 'none';
                    return;
                }

                const matches = drugNames
                    .filter(d => d.includes(query) && !selectedDrugs.includes(d))
                    .slice(0, 20);

                if (matches.length === 0) {
                    dropdown.style.display = 'none';
                    return;
                }

                matches.forEach(drug => {
                    const li = document.createElement('li');
                    const a = document.createElement('a');
                    a.className = 'dropdown-item';
                    a.href = '#';
                    a.textContent = drug;
                    a.addEventListener('click', e => {
                        e.preventDefault();
                        addDrug(drug);
                    });
                    li.appendChild(a);
                    dropdown.appendChild(li);
                });

                dropdown.style.display = 'block';
            });

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
                    tag.innerHTML = `${drug} <button type="button" class="btn-close" aria-label="Entfernen"></button>`;
                    tag.querySelector('.btn-close').addEventListener('click', () => removeDrug(drug));
                    selectedContainer.appendChild(tag);
                });
            }

            checkBtn.addEventListener('click', async () => {
                if (selectedDrugs.length < 2) return;

                checkBtn.disabled = true;
                checkBtnSpinner.style.display = 'inline-block';
                checkBtnText.textContent = 'Prüfe...';
                resultsDiv.style.display = 'none';

                try {
                    const res = await fetch('/api/interactions', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ drugs: selectedDrugs }),
                    });

                    const data = await res.json();

                    resultsDiv.style.display = 'block';

                    if (data.count === 0) {
                        resultsDiv.innerHTML = `
                            <div class="alert alert-success">
                                <strong>Keine Wechselwirkungen gefunden</strong> zwischen den ausgewählten Medikamenten.
                            </div>`;
                    } else {
                        let html = `
                            <div class="alert alert-warning">
                                <strong>${data.count} Wechselwirkung(en) gefunden:</strong>
                            </div>
                            <ul class="list-group">`;

                        data.interactions.forEach(inter => {
                            const severityBadge = inter.severity
                                ? `<span class="badge bg-danger ms-2">${inter.severity}</span>`
                                : '';

                            html += `
                                <li class="list-group-item">
                                    <strong>${inter.drug1}</strong> &harr; <strong>${inter.drug2}</strong>
                                    ${severityBadge}
                                    <br><small class="text-muted">${inter.label || 'Unbekannte Wechselwirkung'}</small>
                                </li>`;
                        });

                        html += '</ul>';
                        resultsDiv.innerHTML = html;
                    }
                } catch {
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
