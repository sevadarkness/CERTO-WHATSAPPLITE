<?php
require_once __DIR__ . '/session_bootstrap.php';
include 'db_config.php';  // Conexão com o banco de dados
include 'menu_navegacao.php';  // Inclusão do menu de navegação
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="alabama-theme.css">

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Conectar Cliente ao Vendedor Mais Próximos</title>
 
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <style>
    body {
      font-family: 'Arial', sans-serif;
      background-color: #f9f9f9;
      margin-top: 0;
    }

    h1, h3 {
      color: #333;
      text-align: center;
    }

    input, button {
      margin-bottom: 10px;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-size: 1rem;
      width: 100%;
    }

    button {
      background-color: #007bff;
      color: white;
      cursor: pointer;
      transition: background-color 0.3s;
    }

    button:hover {
      background-color: #0056b3;
    }

    table {
      margin-top: 20px;
      width: 100%;
      border-collapse: collapse;
    }

    th, td {
      text-align: center;
      padding: 10px;
    }

    th {
      background-color: #007bff;
      color: white;
      font-weight: bold;
    }

    tr:nth-child(even) {
      background-color: #f2f2f2;
    }

    #map {
      margin-top: 20px;
      height: 400px;
      width: 100%;
      border: 2px solid #007bff;
      border-radius: 5px;
    }

    .container {
      max-width: 800px;
      margin: 0 auto;
    }

    #sales-info {
      margin-top: 20px;
      background: #fff;
      padding: 15px;
      border: 1px solid #ccc;
      border-radius: 5px;
    }

    #sales-info textarea {
      width: 100%;
      height: 120px;
      margin-top: 10px;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-family: 'Arial', sans-serif;
      font-size: 1rem;
      line-height: 1.5;
    }

    ul {
      padding-left: 20px;
      list-style: none;
    }

    ul li {
      font-size: 1.1rem;
      margin: 5px 0;
    }

    nav {
      width: 100%;
      position: fixed;
      top: 0;
      left: 0;
      z-index: 1000;
      background-color: #fff;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    body {
      padding-top: 0;
    }

    .container {
      margin-top: 70px;
    }
  </style>
</head>
<body>

  <div class="container">
    <h1>Conectar Cliente ao Vendedor Mais Próximos</h1>

    <h3>Localização dos Vendedores</h3>
    <div id="vendedores-inputs">
      <label>Vendedor 1 (LUIZ HENRIQUE):</label>
      <input id="vendedor1" type="text" value="Rua Baronesa de Guararema, Rio de Janeiro">
      <label>Vendedor 2 (LUIZ FERNANDO):</label>
      <input id="vendedor2" type="text" value="Rua Cândido Mendes, 51 - Parque Felicidade, Rio de Janeiro">
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

  <!-- Google Maps JavaScript API -->
  <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo urlencode(getenv('GOOGLE_MAPS_API_KEY')?:getenv('ALABAMA_GOOGLE_MAPS_API_KEY')?:''); ?>&libraries=places"></script>
  <script <?php echo alabama_csp_nonce_attr(); ?>>
    let map, geocoder, service;
    let vendedor1LatLng, vendedor2LatLng, clienteLatLng;
    let vendedor1Marker, vendedor2Marker;

    function initMap() {
      // Centralizar no Rio de Janeiro
      const rioDeJaneiro = { lat: -22.9068, lng: -43.1729 };
      map = new google.maps.Map(document.getElementById('map'), {
        center: rioDeJaneiro,
        zoom: 12,
      });

      geocoder = new google.maps.Geocoder();
      service = new google.maps.DistanceMatrixService();

      // AutoComplete para o campo de endereço do cliente
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
      // Geocode as localizações dos vendedores
      const enderecoVendedor1 = document.getElementById('vendedor1').value;
      const enderecoVendedor2 = document.getElementById('vendedor2').value;

      geocodeEndereco(enderecoVendedor1, 'vendedor1');
      geocodeEndereco(enderecoVendedor2, 'vendedor2');
    }

    function geocodeEndereco(endereco, vendedor) {
      geocoder.geocode({ address: endereco }, function(results, status) {
        if (status === 'OK') {
          const latLng = results[0].geometry.location;
          if (vendedor === 'vendedor1') {
            vendedor1LatLng = latLng;
            vendedor1Marker = new google.maps.Marker({
              map: map,
              position: latLng,
              title: "Vendedor 1",
            });
          } else if (vendedor === 'vendedor2') {
            vendedor2LatLng = latLng;
            vendedor2Marker = new google.maps.Marker({
              map: map,
              position: latLng,
              title: "Vendedor 2",
            });
          }

          // Traçar a linha para cada vendedor
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
          alert('Geocode não foi bem-sucedido para o endereço ' + endereco);
        }
      });
    }

    function findClosestVendedor() {
      const clienteEndereco = document.getElementById('cliente').value;
      geocoder.geocode({ address: clienteEndereco }, function(results, status) {
        if (status === 'OK') {
          clienteLatLng = results[0].geometry.location;

          // Calcular a distância entre o cliente e os vendedores
          const request = {
            origins: [clienteLatLng],
            destinations: [vendedor1LatLng, vendedor2LatLng],
            travelMode: 'DRIVING',
          };

          service.getDistanceMatrix(request, function(response, status) {
            if (status === 'OK') {
              const distances = response.rows[0].elements;
              let resultHtml = "<table><tr><th>Vendedor</th><th>Distância</th></tr>";
              let saleDetails = "";
              let availabilityList = [];

              distances.forEach((distance, index) => {
                const vendedor = index === 0 ? 'Vendedor 1 (LUIZ HENRIQUE)' : 'Vendedor 2 (LUIZ FERNANDO)';
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
          alert('Erro ao geocodificar o endereço do cliente: ' + status);
        }
      });
    }

    google.maps.event.addDomListener(window, 'load', initMap);
  </script>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
</body>
</html>
 <?php include 'footer.php'; ?>
