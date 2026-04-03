//  Basic Map //
var basic = new Datamap({
  element: document.getElementById('basic'),
});
// Basic Choropleth //
var basic_choropleth = new Datamap({
  element: document.getElementById('basic_choropleth'),
  projection: 'mercator',
  fills: {
    defaultFill: 'var(--success)',
    authorHasTraveledTo: 'var(--primary)',
  },
  data: {
    USA: { fillKey: 'authorHasTraveledTo' },
    JPN: { fillKey: 'authorHasTraveledTo' },
    ITA: { fillKey: 'authorHasTraveledTo' },
    CRI: { fillKey: 'authorHasTraveledTo' },
    KOR: { fillKey: 'authorHasTraveledTo' },
    DEU: { fillKey: 'authorHasTraveledTo' },
  },
});
var colors = d3.scale.category10();
window.setInterval(function () {
  basic_choropleth.updateChoropleth({
    USA: colors(Math.random() * 10),
    RUS: colors(Math.random() * 100),
    AUS: { fillKey: 'authorHasTraveledTo' },
    BRA: colors(Math.random() * 50),
    CAN: colors(Math.random() * 50),
    ZAF: colors(Math.random() * 50),
    IND: colors(Math.random() * 50),
  });
}, 2000);
// Map Election //
var election = new Datamap({
  scope: 'usa',
  element: document.getElementById('map_election'),
  geographyConfig: {
    highlightBorderColor: '#bada55',
    highlightBorderWidth: 3,
  },
  fills: {
    Republican: '#CC4731',
    Democrat: '#306596',
    'Heavy Democrat': '#667FAF',
    'Light Democrat': '#A9C0DE',
    'Heavy Republican': '#CA5E5B',
    'Light Republican': '#EAA9A8',
    defaultFill: '#EDDC4E',
  },
  data: {
    AZ: {
      fillKey: 'Republican',
      electoralVotes: 5,
    },
    CO: {
      fillKey: 'Light Democrat',
      electoralVotes: 5,
    },
    DE: {
      fillKey: 'Democrat',
      electoralVotes: 32,
    },
    FL: {
      fillKey: 'UNDECIDED',
      electoralVotes: 29,
    },
    GA: {
      fillKey: 'Republican',
      electoralVotes: 32,
    },
    HI: {
      fillKey: 'Democrat',
      electoralVotes: 32,
    },
    ID: {
      fillKey: 'Republican',
      electoralVotes: 32,
    },
    IL: {
      fillKey: 'Democrat',
      electoralVotes: 32,
    },
    IN: {
      fillKey: 'Republican',
      electoralVotes: 11,
    },
    IA: {
      fillKey: 'Light Democrat',
      electoralVotes: 11,
    },
    KS: {
      fillKey: 'Republican',
      electoralVotes: 32,
    },
    KY: {
      fillKey: 'Republican',
      electoralVotes: 32,
    },
    LA: {
      fillKey: 'Republican',
      electoralVotes: 32,
    },
    MD: {
      fillKey: 'Democrat',
      electoralVotes: 32,
    },
    ME: {
      fillKey: 'Democrat',
      electoralVotes: 32,
    },
    MA: {
      fillKey: 'Democrat',
      electoralVotes: 32,
    },
    MN: {
      fillKey: 'Democrat',
      electoralVotes: 32,
    },
    MI: {
      fillKey: 'Democrat',
      electoralVotes: 32,
    },
    MS: {
      fillKey: 'Republican',
      electoralVotes: 32,
    },
    MO: {
      fillKey: 'Republican',
      electoralVotes: 13,
    },
    MT: {
      fillKey: 'Republican',
      electoralVotes: 32,
    },
    NC: {
      fillKey: 'Light Republican',
      electoralVotes: 32,
    },
    NE: {
      fillKey: 'Republican',
      electoralVotes: 32,
    },
    NV: {
      fillKey: 'Heavy Democrat',
      electoralVotes: 32,
    },
    NH: {
      fillKey: 'Light Democrat',
      electoralVotes: 32,
    },
    NJ: {
      fillKey: 'Democrat',
      electoralVotes: 32,
    },
    NY: {
      fillKey: 'Democrat',
      electoralVotes: 32,
    },
    ND: {
      fillKey: 'Republican',
      electoralVotes: 32,
    },
    NM: {
      fillKey: 'Democrat',
      electoralVotes: 32,
    },
    OH: {
      fillKey: 'UNDECIDED',
      electoralVotes: 32,
    },
    OK: {
      fillKey: 'Republican',
      electoralVotes: 32,
    },
    OR: {
      fillKey: 'Democrat',
      electoralVotes: 32,
    },
    PA: {
      fillKey: 'Democrat',
      electoralVotes: 32,
    },
    RI: {
      fillKey: 'Democrat',
      electoralVotes: 32,
    },
    SC: {
      fillKey: 'Republican',
      electoralVotes: 32,
    },
    SD: {
      fillKey: 'Republican',
      electoralVotes: 32,
    },
    TN: {
      fillKey: 'Republican',
      electoralVotes: 32,
    },
    TX: {
      fillKey: 'Republican',
      electoralVotes: 32,
    },
    UT: {
      fillKey: 'Republican',
      electoralVotes: 32,
    },
    WI: {
      fillKey: 'Democrat',
      electoralVotes: 32,
    },
    VA: {
      fillKey: 'Light Democrat',
      electoralVotes: 32,
    },
    VT: {
      fillKey: 'Democrat',
      electoralVotes: 32,
    },
    WA: {
      fillKey: 'Democrat',
      electoralVotes: 32,
    },
    WV: {
      fillKey: 'Republican',
      electoralVotes: 32,
    },
    WY: {
      fillKey: 'Republican',
      electoralVotes: 32,
    },
    CA: {
      fillKey: 'Democrat',
      electoralVotes: 32,
    },
    CT: {
      fillKey: 'Democrat',
      electoralVotes: 32,
    },
    AK: {
      fillKey: 'Republican',
      electoralVotes: 32,
    },
    AR: {
      fillKey: 'Republican',
      electoralVotes: 32,
    },
    AL: {
      fillKey: 'Republican',
      electoralVotes: 32,
    },
  },
});
election.labels();
// Area Bubbles //
var bubble_map = new Datamap({
  element: document.getElementById('bubbles'),
  geographyConfig: {
    popupOnHover: false,
    highlightOnHover: false,
  },
  fills: {
    defaultFill: '#ABDDA4',
    USA: 'blue',
    RUS: 'red',
  },
});
bubble_map.bubbles(
  [
    {
      name: 'Not a bomb, but centered on Brazil',
      radius: 23,
      centered: 'BRA',
      country: 'USA',
      yeild: 0,
      fillKey: 'USA',
      date: '1954-03-01',
    },
    {
      name: 'Not a bomb',
      radius: 15,
      yeild: 0,
      country: 'USA',
      centered: 'USA',
      date: '1986-06-05',
      significance: 'Centered on US',
      fillKey: 'USA',
    },
    {
      name: 'Castle Bravo',
      radius: 25,
      yeild: 15000,
      country: 'USA',
      significance: 'First dry fusion fuel "staged" thermonuclear weapon; a serious nuclear fallout accident occurred',
      fillKey: 'USA',
      date: '1954-03-01',
      latitude: 11.415,
      longitude: 165.1619,
    },
    {
      name: 'Tsar Bomba',
      radius: 70,
      yeild: 50000,
      country: 'USSR',
      fillKey: 'RUS',
      significance: 'Largest thermonuclear weapon ever tested—scaled down from its initial 100 Mt design by 50%',
      date: '1961-10-31',
      latitude: 73.482,
      longitude: 54.5854,
    },
  ],
  {},
);
// Projection Map //
var map = new Datamap({
  scope: 'world',
  element: document.getElementById('projection_map'),
  projection: 'orthographic',
  fills: {
    defaultFill: '#ABDDA4',
    gt50: colors(Math.random() * 20),
    eq50: colors(Math.random() * 20),
    lt25: colors(Math.random() * 10),
    gt75: colors(Math.random() * 200),
    lt50: colors(Math.random() * 20),
    eq0: colors(Math.random() * 1),
    pink: '#0fa0fa',
    gt500: colors(Math.random() * 1),
  },
  projectionConfig: {
    rotation: [97, -30],
  },
  data: {
    USA: { fillKey: 'lt50' },
    MEX: { fillKey: 'lt25' },
    CAN: { fillKey: 'gt50' },
    GTM: { fillKey: 'gt500' },
    HND: { fillKey: 'eq50' },
    BLZ: { fillKey: 'pink' },
    GRL: { fillKey: 'eq0' },
    CAN: { fillKey: 'gt50' },
  },
});
map.graticule();
map.arc(
  [
    {
      origin: {
        latitude: 61,
        longitude: -149,
      },
      destination: {
        latitude: -22,
        longitude: -43,
      },
    },
  ],
  {
    greatArc: true,
    animationSpeed: 2000,
  },
);
// Con Fig  //
var defaultOptions = {
  scope: 'world', //currently supports 'usa' and 'world', however with custom map data you can specify your own
  //returns a d3 path and projection functions
  projection: 'equirectangular', //style of projection to be used. try "mercator"
  height: null, //if not null, datamaps will grab the height of 'element'
  width: null, //if not null, datamaps will grab the width of 'element'
  responsive: false, //if true, call `resize()` on the map object when it should adjust it's size
  done: function () {}, //callback when the map is done drawing
  fills: {
    defaultFill: '#ABDDA4', //the keys in this object map to the "fillKey" of [data] or [bubbles]
  },
  dataType: 'json', //for use with dataUrl, currently 'json' or 'csv'. CSV should have an `id` column
  dataUrl: null, //if not null, datamaps will attempt to fetch this based on dataType ( default: json )
  geographyConfig: {
    dataUrl: null, //if not null, datamaps will fetch the map JSON (currently only supports topojson)
    hideAntarctica: true,
    borderWidth: 1,
    borderOpacity: 1,
    borderColor: '#FDFDFD',
    popupTemplate: function (geography, data) {
      //this function should just return a string
      return '<div class="hoverinfo"><strong>' + geography.properties.name + '</strong></div>';
    },
    popupOnHover: true, //disable the popup while hovering
    highlightOnHover: true,
    highlightFillColor: '#FC8D59',
    highlightBorderColor: 'rgba(250, 15, 160, 0.2)',
    highlightBorderWidth: 2,
    highlightBorderOpacity: 1,
  },
  bubblesConfig: {
    borderWidth: 2,
    borderOpacity: 1,
    borderColor: '#FFFFFF',
    popupOnHover: true,
    radius: null,
    popupTemplate: function (geography, data) {
      return '<div class="hoverinfo"><strong>' + data.name + '</strong></div>';
    },
    fillOpacity: 0.75,
    animate: true,
    highlightOnHover: true,
    highlightFillColor: '#FC8D59',
    highlightBorderColor: 'rgba(250, 15, 160, 0.2)',
    highlightBorderWidth: 2,
    highlightBorderOpacity: 1,
    highlightFillOpacity: 0.85,
    exitDelay: 100,
    key: JSON.stringify,
  },
  arcConfig: {
    strokeColor: '#DD1C77',
    strokeWidth: 1,
    arcSharpness: 1,
    animationSpeed: 600,
  },
};