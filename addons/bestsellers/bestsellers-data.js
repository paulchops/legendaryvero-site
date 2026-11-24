/* Best-seller add-on: DATA for one client (Legendary Vero, apparel line).
   SAMPLE items and SAMPLE prices. Robert supplies the real list (one spreadsheet row per item);
   this file is generated from that spreadsheet. Nothing here is a real price.

   Shape (all fields per item):
     id, category, name, style (maker + style number), methods, price (from, each, with logo),
     basis (what the "from" price assumes), min, turn, palette (key into palettes), sizes, placements, desc
   Optional per client: tiers (quantity breaks and multipliers), shots (photo slot names). */
window.BESTSELLERS = {
  line: "Apparel",
  sample: true,
  note: "Sample items and prices. Robert supplies the real list.",
  categories: ["T-shirts", "Polos", "Hoodies & jackets", "Hats", "Workwear"],
  tiers: [[12, 1.3], [24, 1], [48, 0.85], ["72+", 0.75]],
  shots: ["Front", "Back", "On a real job", "Logo close-up"],
  palettes: {
    apparel: [["Black", "#1B1D21"], ["White", "#FFFFFF"], ["Navy", "#1F2A44"], ["Royal", "#2B4FB3"], ["Red", "#B91C1C"], ["Sport Grey", "#A3A8AE"], ["Forest", "#24452F"], ["Maroon", "#5B1A24"]],
    hats: [["Black/White", "#1B1D21"], ["Navy/White", "#1F2A44"], ["Charcoal/Black", "#3F4246"], ["Loden/Black", "#4E5638"], ["Heather/Black", "#8E9399"]],
    work: [["Navy", "#1F2A44"], ["Charcoal", "#3F4246"], ["Light Blue", "#9DB8D6"], ["Khaki", "#B9A97F"], ["White", "#FFFFFF"]]
  },
  items: [
    { id: 1, category: "T-shirts", name: "Heavy Cotton Tee", style: "Gildan 5000", methods: "Screen print · DTG", price: 9, basis: "at 24 pcs, 1-color front", min: 12, turn: "7–10 days", palette: "apparel", sizes: "S–5XL", placements: "Full front, back, left chest, sleeve", desc: "An everyday crew neck that takes print well and holds up wash after wash." },
    { id: 2, category: "T-shirts", name: "Jersey Tee (soft, retail fit)", style: "Bella+Canvas 3001", methods: "Screen print · DTG", price: 12, basis: "at 24 pcs, 1-color front", min: 12, turn: "7–10 days", palette: "apparel", sizes: "XS–4XL", placements: "Full front, back, left chest, sleeve", desc: "A lighter, softer tee with a modern fit. Popular for events and retail." },
    { id: 3, category: "T-shirts", name: "Garment-Dyed Tee", style: "Comfort Colors 1717", methods: "Screen print · DTG", price: 16, basis: "at 24 pcs, 1-color front", min: 12, turn: "7–10 days", palette: "apparel", sizes: "S–4XL", placements: "Full front, back, left chest, sleeve", desc: "Thick, washed-soft cotton in relaxed colors." },
    { id: 4, category: "T-shirts", name: "Performance Tee", style: "Sport-Tek ST350", methods: "Screen print · Sublimation", price: 11, basis: "at 24 pcs, 1-color front", min: 12, turn: "7–10 days", palette: "apparel", sizes: "XS–4XL", placements: "Full front, back, sleeve", desc: "Moisture-wicking polyester for teams, runs and outdoor work." },
    { id: 5, category: "Polos", name: "Silk Touch Polo", style: "Port Authority K500", methods: "Embroidery", price: 24, basis: "at 24 pcs, left-chest logo", min: 6, turn: "7–10 days", palette: "apparel", sizes: "XS–6XL", placements: "Left chest, right chest, back yoke, sleeve", desc: "A sharp, wrinkle-resistant polo for staff, teams and events." },
    { id: 6, category: "Polos", name: "Moisture-Wicking Polo", style: "Sport-Tek ST650", methods: "Embroidery", price: 22, basis: "at 24 pcs, left-chest logo", min: 6, turn: "7–10 days", palette: "apparel", sizes: "XS–4XL", placements: "Left chest, right chest, back yoke, sleeve", desc: "Light and cool for Florida heat, with a clean embroidered logo." },
    { id: 7, category: "Hoodies & jackets", name: "Heavy Blend Hoodie", style: "Gildan 18500", methods: "Screen print · Embroidery", price: 26, basis: "at 24 pcs, 1-color front", min: 12, turn: "7–10 days", palette: "apparel", sizes: "S–5XL", placements: "Full front, left chest, back, sleeve", desc: "Warm, durable pullover that looks good with a big print or a stitched logo." },
    { id: 8, category: "Hoodies & jackets", name: "Midweight Hoodie", style: "Independent SS4500", methods: "Screen print · Embroidery", price: 34, basis: "at 24 pcs, 1-color front", min: 12, turn: "7–10 days", palette: "apparel", sizes: "XS–3XL", placements: "Full front, left chest, back, sleeve", desc: "A softer, retail-quality hoodie for merch and gifts." },
    { id: 9, category: "Hoodies & jackets", name: "Soft Shell Jacket", style: "Port Authority J317", methods: "Embroidery", price: 45, basis: "at 24 pcs, left-chest logo", min: 6, turn: "10–14 days", palette: "apparel", sizes: "XS–6XL", placements: "Left chest, right chest, back", desc: "Wind- and water-resistant jacket for staff and crews." },
    { id: 10, category: "Hats", name: "Trucker Cap", style: "Richardson 112", methods: "Embroidery · Patch", price: 18, basis: "at 24 pcs, front logo", min: 12, turn: "7–10 days", palette: "hats", sizes: "One size, adjustable", placements: "Front, side, back", desc: "Structured cap with a front panel made for embroidery or a leather patch." },
    { id: 11, category: "Hats", name: "Knit Beanie", style: "Port & Company CP90", methods: "Embroidery", price: 12, basis: "at 24 pcs, front logo", min: 12, turn: "7–10 days", palette: "hats", sizes: "One size", placements: "Front cuff", desc: "Simple cuffed beanie with a stitched logo." },
    { id: 12, category: "Workwear", name: "Industrial Work Shirt", style: "Red Kap SP14", methods: "Embroidery · Name patch", price: 32, basis: "at 24 pcs, logo + name", min: 6, turn: "10–14 days", palette: "work", sizes: "S–5XL, tall sizes", placements: "Left chest logo, right chest name patch, back", desc: "Tough, stain-resistant work shirt with room for your logo and each employee’s name." }
  ]
};
