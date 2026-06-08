function showToast(message, type = "success") {
    const toast = document.createElement("div");

    toast.innerText = message;

    toast.style.position = "fixed";
    toast.style.bottom = "20px";
    toast.style.right = "20px";
    toast.style.padding = "12px 16px";
    toast.style.borderRadius = "8px";
    toast.style.color = "#fff";
    toast.style.zIndex = "9999";
    toast.style.fontSize = "14px";

    toast.style.background =
        type === "success" ? "#2ecc71" :
        type === "error" ? "#e74c3c" : "#3498db";

    document.body.appendChild(toast);

    setTimeout(() => toast.remove(), 2500);
}