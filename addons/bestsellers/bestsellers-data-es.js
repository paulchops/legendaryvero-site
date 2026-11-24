/* Best-seller add-on: SPANISH data for Legendary Vero (apparel line), used on es/ pages. Same items as bestsellers-data.js
   (keep the two in step; same ids, prices and style numbers). SAMPLE items and prices. "usted" throughout. 2026-10-17. */
window.BESTSELLERS = {
  line: "Ropa",
  sample: true,
  note: "Artículos y precios de muestra. Robert nos dará la lista real.",
  labels: { all: "Todo", photos: "Fotos y detalles: ", sample: "Muestra", photoPh: "foto", finished: "trabajo terminado", peek: "Fotos y detalles",
    style: "Estilo", from: "Desde $", each: "c/u con su logo", min: "Mín.", quote: "Cotizar este artículo", quoteIn: "Cotizar este artículo en ", close: "Cerrar",
    largePh: "foto grande", color: "Color", colors: "colores", sizes: "Tallas", decoration: "Decoración", placement: "Ubicación del logo",
    minTurn: "Mínimo · tiempo de entrega", pcs: "pzas", priceEach: "Precio por pieza con su logo", samplePrices: "Precios de muestra.", picked: "Usted eligió" },
  categories: ["Camisetas", "Polos", "Sudaderas y chaquetas", "Gorras", "Ropa de trabajo"],
  tiers: [[12, 1.3], [24, 1], [48, 0.85], ["72+", 0.75]],
  shots: ["Frente", "Espalda", "En un trabajo real", "Detalle del logo"],
  palettes: {
    apparel: [["Negro", "#1B1D21"], ["Blanco", "#FFFFFF"], ["Azul marino", "#1F2A44"], ["Azul rey", "#2B4FB3"], ["Rojo", "#B91C1C"], ["Gris deportivo", "#A3A8AE"], ["Verde bosque", "#24452F"], ["Granate", "#5B1A24"]],
    hats: [["Negro/Blanco", "#1B1D21"], ["Azul marino/Blanco", "#1F2A44"], ["Carbón/Negro", "#3F4246"], ["Verde loden/Negro", "#4E5638"], ["Jaspeado/Negro", "#8E9399"]],
    work: [["Azul marino", "#1F2A44"], ["Carbón", "#3F4246"], ["Azul claro", "#9DB8D6"], ["Caqui", "#B9A97F"], ["Blanco", "#FFFFFF"]]
  },
  items: [
    { id: 1, category: "Camisetas", name: "Camiseta de algodón grueso", style: "Gildan 5000", methods: "Serigrafía · DTG", price: 9, basis: "a 24 pzas, frente a 1 color", min: 12, turn: "7–10 días", palette: "apparel", sizes: "S–5XL", placements: "Frente completo, espalda, pecho izquierdo, manga", desc: "Una camiseta de cuello redondo para todos los días, que toma bien la impresión y aguanta lavada tras lavada." },
    { id: 2, category: "Camisetas", name: "Camiseta jersey (suave, corte moderno)", style: "Bella+Canvas 3001", methods: "Serigrafía · DTG", price: 12, basis: "a 24 pzas, frente a 1 color", min: 12, turn: "7–10 días", palette: "apparel", sizes: "XS–4XL", placements: "Frente completo, espalda, pecho izquierdo, manga", desc: "Más ligera y suave, con un corte moderno. Popular para eventos y venta al público." },
    { id: 3, category: "Camisetas", name: "Camiseta teñida en prenda", style: "Comfort Colors 1717", methods: "Serigrafía · DTG", price: 16, basis: "a 24 pzas, frente a 1 color", min: 12, turn: "7–10 días", palette: "apparel", sizes: "S–4XL", placements: "Frente completo, espalda, pecho izquierdo, manga", desc: "Algodón grueso, suave por el lavado, en colores relajados." },
    { id: 4, category: "Camisetas", name: "Camiseta deportiva", style: "Sport-Tek ST350", methods: "Serigrafía · Sublimación", price: 11, basis: "a 24 pzas, frente a 1 color", min: 12, turn: "7–10 días", palette: "apparel", sizes: "XS–4XL", placements: "Frente completo, espalda, manga", desc: "Poliéster que absorbe la humedad, para equipos, carreras y trabajo al aire libre." },
    { id: 5, category: "Polos", name: "Polo Silk Touch", style: "Port Authority K500", methods: "Bordado", price: 24, basis: "a 24 pzas, logo en el pecho izquierdo", min: 6, turn: "7–10 días", palette: "apparel", sizes: "XS–6XL", placements: "Pecho izquierdo, pecho derecho, espalda alta, manga", desc: "Un polo elegante que no se arruga, para personal, equipos y eventos." },
    { id: 6, category: "Polos", name: "Polo que absorbe la humedad", style: "Sport-Tek ST650", methods: "Bordado", price: 22, basis: "a 24 pzas, logo en el pecho izquierdo", min: 6, turn: "7–10 días", palette: "apparel", sizes: "XS–4XL", placements: "Pecho izquierdo, pecho derecho, espalda alta, manga", desc: "Ligero y fresco para el calor de Florida, con un logo bordado limpio." },
    { id: 7, category: "Sudaderas y chaquetas", name: "Sudadera con capucha Heavy Blend", style: "Gildan 18500", methods: "Serigrafía · Bordado", price: 26, basis: "a 24 pzas, frente a 1 color", min: 12, turn: "7–10 días", palette: "apparel", sizes: "S–5XL", placements: "Frente completo, pecho izquierdo, espalda, manga", desc: "Cálida y resistente, se ve bien con una impresión grande o con un logo bordado." },
    { id: 8, category: "Sudaderas y chaquetas", name: "Sudadera con capucha de peso medio", style: "Independent SS4500", methods: "Serigrafía · Bordado", price: 34, basis: "a 24 pzas, frente a 1 color", min: 12, turn: "7–10 días", palette: "apparel", sizes: "XS–3XL", placements: "Frente completo, pecho izquierdo, espalda, manga", desc: "Una sudadera más suave, de calidad de tienda, para mercancía y regalos." },
    { id: 9, category: "Sudaderas y chaquetas", name: "Chaqueta soft shell", style: "Port Authority J317", methods: "Bordado", price: 45, basis: "a 24 pzas, logo en el pecho izquierdo", min: 6, turn: "10–14 días", palette: "apparel", sizes: "XS–6XL", placements: "Pecho izquierdo, pecho derecho, espalda", desc: "Chaqueta resistente al viento y al agua para personal y cuadrillas." },
    { id: 10, category: "Gorras", name: "Gorra de camionero", style: "Richardson 112", methods: "Bordado · Parche", price: 18, basis: "a 24 pzas, logo al frente", min: 12, turn: "7–10 días", palette: "hats", sizes: "Talla única, ajustable", placements: "Frente, lado, atrás", desc: "Gorra estructurada con panel frontal hecho para bordado o parche de cuero." },
    { id: 11, category: "Gorras", name: "Gorro tejido", style: "Port & Company CP90", methods: "Bordado", price: 12, basis: "a 24 pzas, logo al frente", min: 12, turn: "7–10 días", palette: "hats", sizes: "Talla única", placements: "Doblez del frente", desc: "Gorro sencillo con doblez y logo bordado." },
    { id: 12, category: "Ropa de trabajo", name: "Camisa de trabajo industrial", style: "Red Kap SP14", methods: "Bordado · Parche con nombre", price: 32, basis: "a 24 pzas, logo + nombre", min: 6, turn: "10–14 días", palette: "work", sizes: "S–5XL, tallas largas", placements: "Logo en el pecho izquierdo, parche con nombre en el pecho derecho, espalda", desc: "Camisa de trabajo resistente a las manchas, con espacio para su logo y el nombre de cada empleado." }
  ]
};
