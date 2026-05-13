document.addEventListener('DOMContentLoaded', function() {
    const wrapper = document.getElementById('tdaf-wrapper');
    if (!wrapper) return;

    let currentStep = 1;
    const travelers = []; // Additional travelers beyond the main applicant

    // ── DOM Elements ──────────────────────────────────────────────
    const panels = {
        1: document.getElementById('tdaf-panel-1'),
        2: document.getElementById('tdaf-panel-2'),
        3: document.getElementById('tdaf-panel-3'),
        success: document.getElementById('tdaf-panel-success')
    };

    const stepItems = document.querySelectorAll('.tdaf-step-item');
    const summaryRows = document.getElementById('tdaf-summary-rows');
    const totalAmountDisplay = document.getElementById('tdaf-total-amount');
    const addTravelerBtn = document.getElementById('tdaf-add-traveler-btn');
    const travelersContainer = document.getElementById('tdaf-travelers-container');
    const travelerTemplate = document.getElementById('tdaf-traveler-template');

    // ── Pricing Logic ─────────────────────────────────────────────
    const prices = {
        standard: 59.99,
        express: 79.99,
        priority: 109.99,
        esim: 4.00
    };

    function updateSummary() {
        const selectedRadio = document.querySelector('input[name="service"]:checked');
        if (!selectedRadio) return;

        const selectedService = selectedRadio.value;
        const esimAddon = document.getElementById('esim_addon');
        const wantsESIM = esimAddon ? esimAddon.checked : false;
        const personCount = 1 + travelers.length;
        
        const servicePrice = prices[selectedService] || 0;
        const serviceTotal = servicePrice * personCount;
        const esimTotal = wantsESIM ? (prices.esim * personCount) : 0;
        const grandTotal = serviceTotal + esimTotal;

        const serviceName = {
            standard: 'Standard Processing (24h)',
            express: 'Express Processing (4h)',
            priority: 'Priority Processing (1h)'
        }[selectedService] || 'Selected Service';

        if (summaryRows) {
            summaryRows.innerHTML = `
                <tr class="flex flex-col sm:table-row p-4 sm:p-0 border-b transition-colors hover:bg-slate-100/50">
                    <td class="sm:p-4 align-middle font-bold sm:font-normal">${serviceName}</td>
                    <td class="sm:p-4 align-middle sm:text-center text-slate-500"><span class="sm:hidden">Qty: </span>${personCount}</td>
                    <td class="sm:p-4 align-middle sm:text-right font-medium text-red-600 sm:text-slate-900">$${serviceTotal.toFixed(2)}</td>
                </tr>
                ${wantsESIM ? `
                <tr class="flex flex-col sm:table-row p-4 sm:p-0 border-b transition-colors hover:bg-slate-100/50">
                    <td class="sm:p-4 align-middle font-bold sm:font-normal">Travel eSIM (Unlimited Data)</td>
                    <td class="sm:p-4 align-middle sm:text-center text-slate-500"><span class="sm:hidden">Qty: </span>${personCount}</td>
                    <td class="sm:p-4 align-middle sm:text-right font-medium text-red-600 sm:text-slate-900">$${esimTotal.toFixed(2)}</td>
                </tr>
                ` : ''}
            `;
        }
        
        if (totalAmountDisplay) {
            totalAmountDisplay.textContent = `$${grandTotal.toFixed(2)}`;
        }
    }

    // ── Step Navigation ───────────────────────────────────────────
    function showStep(step) {
        // Hide all panels
        Object.values(panels).forEach(p => p.classList.add('hidden'));
        
        // Show current panel
        panels[step].classList.remove('hidden');

        // Update indicators
        stepItems.forEach(item => {
            const itemStep = parseInt(item.dataset.step);
            item.classList.remove('active', 'completed');
            if (itemStep === step) {
                item.classList.add('active');
            } else if (itemStep < step) {
                item.classList.add('completed');
            }
        });

        currentStep = step;
        window.scrollTo({ top: wrapper.offsetTop - 50, behavior: 'smooth' });
        
        if (step === 3) updateSummary();
    }

    // Next Buttons
    document.getElementById('tdaf-next-1').addEventListener('click', () => showStep(2));
    document.getElementById('tdaf-next-2').addEventListener('click', () => showStep(3));

    // Previous Buttons
    document.getElementById('tdaf-prev-2').addEventListener('click', () => showStep(1));
    document.getElementById('tdaf-prev-3').addEventListener('click', () => showStep(2));

    // ── Traveler Management ───────────────────────────────────────
    function addTraveler() {
        const clone = travelerTemplate.content.cloneNode(true);
        const card = clone.querySelector('.tdaf-traveler-card');
        
        travelersContainer.appendChild(card);
        travelers.push({}); // Keep array in sync
        
        reindexTravelers();

        // Remove button logic
        card.querySelector('.tdaf-remove-traveler').addEventListener('click', function() {
            card.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                card.remove();
                reindexTravelers();
            }, 300);
        });
    }

    function reindexTravelers() {
        const cards = travelersContainer.querySelectorAll('.tdaf-traveler-card');
        cards.forEach((card, i) => {
            card.querySelector('.tdaf-traveler-num').textContent = i + 2;
            card.querySelectorAll('input, select').forEach(el => {
                const name = el.getAttribute('name');
                if (name) {
                    el.setAttribute('name', name.replace(/travelers\[\d+\]/, `travelers[${i}]`));
                }
            });
        });
        
        travelers.length = cards.length;
        updateSummary();
    }

    if (addTravelerBtn) {
        addTravelerBtn.addEventListener('click', addTraveler);
    }

    // ── Final Submission ──────────────────────────────────────────
    const submitBtn = document.getElementById('tdaf-submit');
    if (submitBtn) {
        submitBtn.addEventListener('click', function() {
            const btn = this;
            const originalContent = btn.innerHTML;
            
            // Collect data
            const formData = new FormData();
            
            // Basic fields
            const inputs = wrapper.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                if (input.type === 'radio' || input.type === 'checkbox') {
                    if (input.checked) formData.append(input.name, input.value);
                } else if (input.type !== 'file') {
                    formData.append(input.name, input.value);
                }
            });

            // Append required AJAX bits
            formData.append('action', 'tdaf_submit_form');
            formData.append('nonce', tdaf_vars.nonce);

            // UI Feedback
            btn.disabled = true;
            btn.innerHTML = '<span class="flex items-center gap-2 justify-center"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Preparing Checkout...</span>';

            // Real AJAX
            jQuery.ajax({
                url: tdaf_vars.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    if (res.success && res.data.payment_url) {
                        window.location.href = res.data.payment_url;
                    } else {
                        alert('Error: ' + (res.data || 'Unknown error occurred.'));
                        btn.disabled = false;
                        btn.innerHTML = originalContent;
                    }
                },
                error: function() {
                    alert('Could not connect to the server. Please try again.');
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                }
            });
        });
    }

    // ── Real-time price listeners ────────────────────────────────
    document.querySelectorAll('input[name="service"]').forEach(radio => {
        radio.addEventListener('change', updateSummary);
    });
    const esimAddon = document.getElementById('esim_addon');
    if (esimAddon) {
        esimAddon.addEventListener('change', updateSummary);
    }

    // Initial State
    showStep(1);
    updateSummary();
});
