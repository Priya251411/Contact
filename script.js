
function validateContactForm(form) {
    const name = form.elements["name"].value.trim();
    const phone = form.elements["phone"].value.trim();
    const email = form.elements["email"].value.trim();
    const address = form.elements["address"].value.trim();

    if (!name || !phone || !email || !address) {
        alert("Please fill in all fields.");
        return false;
    }

    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!emailPattern.test(email)) {
        alert("Please enter a valid email address.");
        return false;
    }

    return true;
}
