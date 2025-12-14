document.addEventListener('DOMContentLoaded', function() {
    const wizards = document.querySelectorAll('.ltlb-wizard-form');
    wizards.forEach(wizard => {
        let currentStep = 1;
        const steps = wizard.querySelectorAll('.ltlb-wizard-step');
        const navSteps = wizard.querySelectorAll('.ltlb-wizard-nav__step');
        const totalSteps = steps.length;

        const showStep = (stepNum) => {
            steps.forEach(step => {
                step.style.display = step.dataset.step == stepNum ? 'block' : 'none';
            });
            navSteps.forEach((nav, index) => {
                const step = index + 1;
                nav.classList.toggle('is-active', step === stepNum);
                nav.classList.toggle('is-complete', step < stepNum);
            });
            currentStep = stepNum;
        };

        wizard.addEventListener('click', function(e) {
            if (e.target.classList.contains('ltlb-wizard-next')) {
                if (currentStep < totalSteps) {
                    showStep(currentStep + 1);
                }
            } else if (e.target.classList.contains('ltlb-wizard-prev')) {
                if (currentStep > 1) {
                    showStep(currentStep - 1);
                }
            }
        });

        showStep(1);
    });
});
