<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Maintenance Checklist | Sonic Elevator Ltd.</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

  <style>
    :root{ --sonic:#00C2CB; }
    body{ background:#f7fbfc; }
    .card-soft{ border:1px solid rgba(15,23,42,.08); box-shadow:0 10px 30px rgba(2,8,23,.06); border-radius:16px; }
    .btn-sonic{ background:var(--sonic); border:none; font-weight:700; }
    .btn-sonic:hover{ background:#06aeb6; }
  </style>
</head>

<body>
  <div class="container py-5">

    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <a href="technician-dashboard.html" class="text-decoration-none">
          <i class="fa-solid fa-arrow-left me-2"></i>Back to Dashboard
        </a>
        <h2 class="fw-bold mt-2 mb-0">Monthly Maintenance Checklist</h2>
        <small class="text-secondary">ELV-DHK-21 • Month: <span id="monthName"></span></small>
      </div>

      <div class="d-flex gap-2">
        <button class="btn btn-outline-dark rounded-pill" onclick="resetChecklist()">
          <i class="fa-solid fa-rotate-left me-2"></i>Reset
        </button>
        <button class="btn btn-sonic rounded-pill" onclick="markComplete()">
          <i class="fa-solid fa-check me-2"></i>Mark Complete
        </button>
      </div>
    </div>

    <div class="card card-soft p-4 mb-4">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="fw-semibold">
          <i class="fa-solid fa-chart-simple me-2" style="color:#00C2CB;"></i>Progress
        </div>
        <span class="badge text-bg-light border"><span id="doneCount">0</span>/<span id="totalCount">0</span> checked</span>
      </div>
      <div class="progress mt-3" style="height:10px;">
        <div id="progressBar" class="progress-bar" style="width:0%; background:#00C2CB;"></div>
      </div>
    </div>

    <div class="card card-soft p-4">
      <h5 class="fw-bold mb-3"><i class="fa-solid fa-list-check me-2" style="color:#00C2CB;"></i>Checklist Items</h5>

      <div id="checklist" class="vstack gap-3"></div>

      <hr class="my-4">
      <div class="d-flex gap-2">
        <input id="newItem" class="form-control" placeholder="Add new checklist item (demo)">
        <button class="btn btn-outline-secondary rounded-pill px-4" onclick="addItem()">
          <i class="fa-solid fa-plus me-2"></i>Add
        </button>
      </div>
      <small class="text-secondary d-block mt-2">Demo uses localStorage. Backend can store per technician/request later.</small>
    </div>

  </div>

<script>
  const KEY = "sonic_checklist_v1";

  const defaultItems = [
    "Check door sensor & door closing speed",
    "Inspect wire ropes / belt condition",
    "Check brake operation & noise",
    "Test emergency alarm & intercom",
    "Inspect control panel and error logs",
    "Verify leveling accuracy at all floors",
    "Check cabin lights & ventilation fan",
    "Test safety gear and overspeed governor",
  ];

  function monthLabel(){
    return new Date().toLocaleDateString(undefined, {month:'long', year:'numeric'});
  }

  function load(){
    document.getElementById("monthName").textContent = monthLabel();

    const saved = JSON.parse(localStorage.getItem(KEY) || "null");
    const items = saved?.items?.length ? saved.items : defaultItems.map(t => ({text:t, done:false}));

    render(items);
  }

  function save(items){
    localStorage.setItem(KEY, JSON.stringify({ items }));
  }

  function render(items){
    const wrap = document.getElementById("checklist");
    wrap.innerHTML = "";

    items.forEach((it, idx) => {
      const row = document.createElement("div");
      row.className = "d-flex align-items-center justify-content-between bg-light rounded p-3";

      row.innerHTML = `
        <div class="form-check m-0">
          <input class="form-check-input" type="checkbox" id="c${idx}" ${it.done ? "checked":""}>
          <label class="form-check-label fw-semibold" for="c${idx}">${it.text}</label>
        </div>
        <button class="btn btn-sm btn-outline-danger rounded-pill" title="Remove" onclick="removeItem(${idx})">
          <i class="fa-solid fa-trash"></i>
        </button>
      `;

      wrap.appendChild(row);

      row.querySelector("input").addEventListener("change", (e) => {
        items[idx].done = e.target.checked;
        save(items);
        updateProgress(items);
      });
    });

    save(items);
    updateProgress(items);
  }

  function updateProgress(items){
    const total = items.length;
    const done = items.filter(x => x.done).length;
    document.getElementById("totalCount").textContent = total;
    document.getElementById("doneCount").textContent = done;
    const pct = total ? Math.round((done/total)*100) : 0;
    document.getElementById("progressBar").style.width = pct + "%";
  }

  function addItem(){
    const text = document.getElementById("newItem").value.trim();
    if(!text) return;

    const saved = JSON.parse(localStorage.getItem(KEY) || "{}");
    const items = saved.items || defaultItems.map(t => ({text:t, done:false}));
    items.push({text, done:false});
    document.getElementById("newItem").value = "";
    render(items);
  }

  function removeItem(i){
    const saved = JSON.parse(localStorage.getItem(KEY) || "{}");
    const items = saved.items || [];
    items.splice(i,1);
    render(items);
  }

  function resetChecklist(){
    if(!confirm("Reset checklist progress?")) return;
    localStorage.removeItem(KEY);
    load();
  }

  function markComplete(){
    const saved = JSON.parse(localStorage.getItem(KEY) || "{}");
    const items = saved.items || [];
    const total = items.length;
    const done = items.filter(x=>x.done).length;

    if(total && done === total){
      alert("✅ Checklist completed and ready to submit (backend can record completion).");
    }else{
      alert("Please complete all checklist items before marking complete.");
    }
  }

  load();
</script>

</body>
</html>