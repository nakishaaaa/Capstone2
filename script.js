function showForm (formId) {
    document.querySelectorAll(".login-card").forEach (form => form.classList.remove("active")); 
    document.getElementById(formId).classList.add("active");
    // Hide admin-switch-btn if admin-form is active, show otherwise
    const adminBtn = document.querySelector('.admin-switch-btn');
    if (formId === 'admin-form') {
        adminBtn.classList.add('hide');
    } else {
        adminBtn.classList.remove('hide');
    }
}