window.onload = function() {

  let prefixUrl = $('#openseadrgagon-view').attr('data-image');
  let url = $('#openseadrgagon-view').attr('data-url')
  const arrayUrl = JSON.parse(url);

  var viewer =  OpenSeadragon({
      id: "openseadrgagon-view",
      prefixUrl: prefixUrl,
      showNavigator: true,
      defaultZoomLevel: 0,
      sequenceMode: true,

      tileSources:
      arrayUrl

    }
  );

}


