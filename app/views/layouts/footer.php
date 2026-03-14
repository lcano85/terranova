<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function() {
        const latEl = document.getElementById('latitude');
        const lngEl = document.getElementById('longitude');

        if (!navigator.geolocation) return;

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                latEl.value = pos.coords.latitude;
                lngEl.value = pos.coords.longitude;
            },
            (err) => {
                // Si el usuario niega permisos, se enviará vacío y el backend guardará NULL.
                console.log('Geolocalización no disponible:', err.message);
            }, {
                enableHighAccuracy: true,
                timeout: 8000,
                maximumAge: 30000
            }
        );
    })();
</script>

</body>

</html>