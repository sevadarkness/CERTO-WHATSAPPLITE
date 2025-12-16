<?php
require_once __DIR__ . '/session_bootstrap.php';
include 'db_config.php';  // Conexão com o banco de dados
include 'menu_navegacao.php';  // Inclusão do menu de navegação
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Frete - Rede Alabama</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/alabama-design-system.css">
  <link rel="stylesheet" href="alabama-theme.css">
  <link rel="stylesheet" href="assets/css/alabama-page-overrides.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .container {
      max-width: 800px;
      margin: 0 auto;
    }

    #map {
      margin-top: 20px;
      height: 400px;
      width: 100%;
      border: 2px solid var(--al-primary);
      border-radius: var(--al-radius-md);
    }

    #sales-info {
      margin-top: 20px;
      padding: 15px;
      border-radius: var(--al-radius-md);
    }

    #sales-info textarea {
      width: 100%;
      height: 120px;
      margin-top: 10px;
      padding: 10px;
      border-radius: var(--al-radius-sm);
      line-height: 1.5;
    }

    ul li {
      font-size: 1.1rem;
      margin: 5px 0;
    }

    .container {
      margin-top: 70px;
    }
  </style>
</head>
<body class="al-body">

  <div class="container">
    <h1>Conectar Cliente ao Vendedor Mais Próximos</h1>

    <h3>Localização dos Vendedores</h3>
    <div id="vendedores-inputs">
      <label>Vendedor 1 (LUIZ HENRIQUE):</label>
      <input id="vendedor1" type="text" value="Rua Baronesa de Guararema, Rio de Janeiro">
      <label>Vendedor 2 (ANA LIVIA):</label>
      <input id="vendedor2" type="text" value="Rua Marlo da Costa e Souza, 135, Rio de Janeiro">
      <label>Vendedor 3 (LAIANE):</label>
      <input id="vendedor3" type="text" value="Estrada do Cambatá, 3083, Rio de Janeiro">
      <label>Vendedor 4 (FLAVIO FRANCISCO):</label>
      <input id="vendedor4" type="text" value="Niteroi, 47, Cpo Lindo, Seropédica - RJ">
      <label>Vendedor 5 (JOANA VITORIA):</label>
      <input id="vendedor5" type="text" value="R. Pajeu, 39 - Jardim Carioca, Rio de Janeiro">
      <button onclick="geocodeVendedores()">Salvar Localizações dos Vendedores</button>
    </div>

    <h3>Localização do Cliente</h3>
    <label for="cliente">Digite o endereço do cliente:</label>
    <input id="cliente" type="text" placeholder="Digite o endereço do cliente">
    <button onclick="findClosestVendedor()">Calcular</button>

    <div id="result"></div>
    <table id="proximity-table"></table>
    <div id="map"></div>

    <div id="sales-info">
      <h3>Detalhes da Venda</h3>
      <p>🕒 Venda solicitada às: <span id="sale-time"></span></p>
      <p>🚚 Ordem de Proximidade:</p>
      <textarea id="sale-details" readonly></textarea>
      <p>📧 Confirmação de Disponibilidade:</p>
      <ul id="availability-list"></ul>
    </div>
  </div>

  <!-- Google Maps JavaScript API (key injected from server env) -->
  <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo urlencode(getenv('GOOGLE_MAPS_API_KEY')?:getenv('ALABAMA_GOOGLE_MAPS_API_KEY')?:''); ?>&libraries=places"></script>
  <script <?php echo alabama_csp_nonce_attr(); ?>>
    let map, geocoder, service;
    let vendedorLatLngs = [];
    let vendedorMarkers = [];
    let clienteLatLng;

    function initMap() {
      const rioDeJaneiro = { lat: -22.9068, lng: -43.1729 };
      map = new google.maps.Map(document.getElementById('map'), {
        center: rioDeJaneiro,
        zoom: 12,
      });

      geocoder = new google.maps.Geocoder();
      service = new google.maps.DistanceMatrixService();

      const input = document.getElementById('cliente');
      const autocomplete = new google.maps.places.Autocomplete(input);
      autocomplete.setFields(['place_id', 'geometry', 'name']);
      autocomplete.addListener('place_changed', onPlaceChanged);
    }

    function onPlaceChanged() {
      const place = this.getPlace();
      if (place.geometry) {
        clienteLatLng = place.geometry.location;
        map.setCenter(clienteLatLng);
        const marker = new google.maps.Marker({
          map: map,
          position: clienteLatLng,
          title: "Cliente",
        });
      }
    }

    function geocodeVendedores() {
      vendedorLatLngs = [];
      vendedorMarkers.forEach(marker => marker.setMap(null));

      const enderecos = [
        document.getElementById('vendedor1').value,
        document.getElementById('vendedor2').value,
        document.getElementById('vendedor3').value,
        document.getElementById('vendedor4').value,
        document.getElementById('vendedor5').value
      ];

      enderecos.forEach((endereco, index) => {
        geocodeEndereco(endereco, index);
      });
    }

    function geocodeEndereco(endereco, index) {
      geocoder.geocode({ address: endereco }, function(results, status) {
        if (status === 'OK') {
          const latLng = results[0].geometry.location;
          vendedorLatLngs[index] = latLng;
          const marker = new google.maps.Marker({
            map: map,
            position: latLng,
            title: `Vendedor ${index + 1}`,
          });
          vendedorMarkers[index] = marker;

          if (clienteLatLng) {
            new google.maps.Polyline({
              path: [latLng, clienteLatLng],
              geodesic: true,
              strokeColor: '#FF0000',
              strokeOpacity: 1.0,
              strokeWeight: 2,
              map: map,
            });
          }
        } else {
          alert(`Geocode não foi bem-sucedido para o endereço: ${endereco}`);
        }
      });
    }

    function findClosestVendedor() {
      const clienteEndereco = document.getElementById('cliente').value;
      geocoder.geocode({ address: clienteEndereco }, function(results, status) {
        if (status === 'OK') {
          clienteLatLng = results[0].geometry.location;

          const request = {
            origins: [clienteLatLng],
            destinations: vendedorLatLngs,
            travelMode: 'DRIVING',
          };

          service.getDistanceMatrix(request, function(response, status) {
            if (status === 'OK') {
              const distances = response.rows[0].elements;
              let resultHtml = "<table><tr><th>Vendedor</th><th>Distância</th></tr>";
              let saleDetails = "";
              let availabilityList = [];

              distances.forEach((distance, index) => {
                const vendedor = `Vendedor ${index + 1}`;
                resultHtml += `<tr><td>${vendedor}</td><td>${distance.distance.text}</td></tr>`;

                saleDetails += `${vendedor}: ${distance.distance.text}\n`;
                availabilityList.push(vendedor);
              });

              resultHtml += "</table>";
              document.getElementById('result').innerHTML = resultHtml;
              document.getElementById('sale-details').value = saleDetails;
              document.getElementById('sale-time').textContent = new Date().toLocaleString();

              const availabilityHtml = availabilityList.map(v => `<li>${v}</li>`).join('');
              document.getElementById('availability-list').innerHTML = availabilityHtml;
            }
          });
        } else {
          alert(`Erro ao geocodificar o endereço do cliente: ${status}`);
        }
      });
    }

    google.maps.event.addDomListener(window, 'load', initMap);
  </script>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
