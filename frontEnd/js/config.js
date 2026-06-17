(function () {
  const pathParts = window.location.pathname.split('/');
  const frontIndex = pathParts.findIndex(part => part.toLowerCase() === 'frontend');

  if (frontIndex >= 0) {
    const projectRoot = pathParts.slice(0, frontIndex).join('/');
    window.API = `${window.location.origin}${projectRoot}/api`;
  } else {
    window.API = `${window.location.origin}/Bloomify/api`;
  }

  console.log("API path:", window.API);
})();