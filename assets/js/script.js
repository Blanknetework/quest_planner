const addQuestBtn = document.getElementById('addQuestBtn');
const addQuestModal = document.getElementById('addQuestModal');
const closeModalBtn = document.getElementById('closeModal');
const addQuestForm = document.getElementById('addQuestForm');
const timerDisplay = document.getElementById('timer');
const currentDateDisplay = document.getElementById('currentDate');
const difficultyButtons = document.querySelectorAll('.difficulty-btn');
const deleteButtons = document.querySelectorAll('.delete-quest');
const completeButtons = document.querySelectorAll('.complete-quest');
const startButtons = document.querySelectorAll('.start-quest');

let selectedDifficulty = 'Easy';

function updateTimer() {
    const now = new Date();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    timerDisplay.textContent = `${hours}:${minutes}:${seconds}`;
}

function updateDate() {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const year = String(now.getFullYear()).slice(-2);
    currentDateDisplay.textContent = `${month}/${day}/${year}`;
}

function initClock() {
    updateTimer();
    updateDate();
    setInterval(updateTimer, 1000);
    setInterval(updateDate, 60000);
}

function openModal() {
    addQuestModal.classList.remove('hidden');
}

function closeModal() {
    addQuestModal.classList.add('hidden');
}

addQuestForm.addEventListener('submit', (e) => {
    const questName = document.getElementById('quest_name').value.trim();
    const difficulty = document.getElementById('difficulty').value;
    const time = document.getElementById('time').value.trim();
    
    if (!questName || !time) {
        e.preventDefault();
        alert('Please fill out all fields');
    }
});

difficultyButtons.forEach(button => {
    button.addEventListener('click', function() {
        difficultyButtons.forEach(btn => {
            btn.style.opacity = '1';
        });
        this.style.opacity = '0.7';
        
        selectedDifficulty = this.dataset.difficulty;
        
        const difficultySelect = document.getElementById('difficulty');
        if (difficultySelect) {
            difficultySelect.value = selectedDifficulty;
        }
    });
});

function setupActionButtons() {
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to delete this quest?')) {
                const questId = this.dataset.id;
                
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_quest';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'quest_id';
                idInput.value = questId;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
    
    completeButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const questId = this.dataset.id;
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'complete_quest';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'quest_id';
            idInput.value = questId;
            
            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        });
    });
    
    startButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const questId = this.dataset.id;
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'start_quest';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'quest_id';
            idInput.value = questId;
            
            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    initClock();
    setupActionButtons();
    
    addQuestBtn.addEventListener('click', openModal);
    closeModalBtn.addEventListener('click', closeModal);
    
    addQuestModal.addEventListener('click', function(e) {
        if (e.target === addQuestModal) {
            closeModal();
        }
    });

    const passwordFields = document.querySelectorAll('.password-field');
    
    passwordFields.forEach(field => {
        const input = field.querySelector('input');
        const toggle = field.querySelector('.password-toggle');
        
        toggle.addEventListener('click', function() {
            input.type = input.type === 'password' ? 'text' : 'password';
        });
    });
});

function adjustUIForViewport() {
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    
    if (viewportWidth <= 768) {
        document.querySelectorAll('.quest-container').forEach(container => {
            container.style.marginBottom = '20px';
        });
        
        const questItemsContainers = document.querySelectorAll('.quest-items-container');
        questItemsContainers.forEach(container => {
            const maxHeight = Math.min(viewportHeight * 0.3, 300);
            container.style.maxHeight = `${maxHeight}px`;
        });
        
        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.style.minWidth = '28px';
            btn.style.minHeight = '28px';
        });
        
    } else if (viewportWidth <= 992) {
        document.querySelectorAll('.quest-container').forEach(container => {
            container.style.marginBottom = '20px';
        });
        
        document.querySelectorAll('.quest-items-container').forEach(container => {
            container.style.maxHeight = '50vh';
        });
        
        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.style.minWidth = '';
            btn.style.minHeight = '';
        });
        
    } else {
        document.querySelectorAll('.quest-container').forEach(container => {
            container.style.marginBottom = '0';
        });
        
        document.querySelectorAll('.quest-items-container').forEach(container => {
            container.style.maxHeight = '60vh';
        });
        
        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.style.minWidth = '';
            btn.style.minHeight = '';
        });
    }
    
    if (viewportWidth <= 320) {
        document.querySelectorAll('.category-header').forEach(header => {
            header.style.fontSize = '12px';
            header.style.padding = '3px 8px';
        });
    }
}

window.addEventListener('load', adjustUIForViewport);
window.addEventListener('resize', adjustUIForViewport);
window.addEventListener('orientationchange', adjustUIForViewport);

function setupTouchEvents() {
    const isTouchDevice = ('ontouchstart' in window) || 
                           (navigator.maxTouchPoints > 0) ||
                           (navigator.msMaxTouchPoints > 0);
    
    if (isTouchDevice) {
        const allButtons = document.querySelectorAll('.menu-button, .category-option, .action-btn, .submit-btn');
        
        allButtons.forEach(button => {
            button.addEventListener('touchstart', function() {
                this.classList.add('touch-active');
            }, { passive: true });
            
            button.addEventListener('touchend', function() {
                this.classList.remove('touch-active');
            }, { passive: true });
            
            button.addEventListener('touchcancel', function() {
                this.classList.remove('touch-active');
            }, { passive: true });
        });
        
        const questContainers = document.querySelectorAll('.quest-items-container');
        questContainers.forEach(container => {
            container.style.webkitOverflowScrolling = 'touch';
        });
        
        const allInteractiveElements = document.querySelectorAll('button, input, select');
        allInteractiveElements.forEach(element => {
            element.addEventListener('touchend', function(e) {
                e.preventDefault();
                this.click();
            }, { passive: false });
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    setupTouchEvents();
});
