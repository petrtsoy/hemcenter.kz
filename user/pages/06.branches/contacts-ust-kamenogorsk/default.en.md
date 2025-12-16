---
title: Ust-Kamenogorsk
hide_page_title: false
show_sidebar: true
hide_git_sync_repo_link: false
visible: true
process:
    markdown: true
    twig: true
---

The branch of LLP “Center of Hematology” in Ust-Kamenogorsk was established in 2015 on the basis of Multidisciplinary Hospital No. 4, which made it possible to preserve the integrated model of a specialized center for providing care to patients with blood disorders within a multidisciplinary healthcare environment.

---

**5 Serikbayev Street, Building 5, Block 1, 2nd floor**

Tel.: **+7 747 095 5650** (weekdays from 08:00 to 17:00)

E-mail: vko@hemcenter.kz

<iframe src="https://yandex.com/map-widget/v1/?um=constructor%3Acfd37c26d969bdd5ee3dfa9a935592062c6803bdbfe5191f0557539facaa818d&amp;source=constructor" width="100%" height="320" frameborder="0"></iframe>

---

All types of services are provided to adult patients with hematological diseases, including high-dose chemotherapy and diagnostic procedures in accordance with international protocols.

Patients of the department have access to consultations with specialized physicians of various profiles, laboratory services, and the necessary instrumental examinations. The branch is equipped with its own intensive care unit.

In addition, the Ust-Kamenogorsk branch has developed a system for providing timely and accessible diagnostic support for studies that are currently unavailable in Kazakhstan. These include molecular genetic tests for diagnosis and monitoring of treatment effectiveness, as well as confirmatory immunohistochemical and histological examinations. For this purpose, the Center maintains partnership relations with leading clinics in Russia and abroad.

The team of specialists at the branch includes highly qualified hematologists, and the branch is headed by a resuscitation and intensive care physician of the highest qualification category, who previously worked for many years at the Cardiac Surgery Center and is skilled in all methods of vascular access and emergency medical care.

[Drug Formulary](../../drug-formulary)

{% set city = 'oskemen' %}
{% set lang = page.language %}

<div class="pricelist-controls" style="margin-bottom:12px;">
  <button id="pl-toggle" type="button">Show pricelist</button>
  <span id="pl-status" style="margin-left:8px; font-size:90%; color:#666;"></span>
</div>

<div id="pricelist-box" hidden>
  <div id="pl-spinner" style="display:none;">Loading…</div>
  <div id="pl-content">
    {{ pricelist_html(city, lang)|raw }}
  </div>
</div>

<script>
(function() {
  var box     = document.getElementById('pricelist-box');
  var btnTgl  = document.getElementById('pl-toggle');
  var btnRef  = document.getElementById('pl-refresh');
  var cont    = document.getElementById('pl-content');
  var spin    = document.getElementById('pl-spinner');
  var status  = document.getElementById('pl-status');
  var city    = {{ city|json_encode|raw }}; // безопасно вставляем значение города

  btnTgl.addEventListener('click', function() {
    if (box.hasAttribute('hidden')) {
      box.removeAttribute('hidden');
      btnTgl.textContent = 'Hide pricelist';
    } else {
      box.setAttribute('hidden', '');
      btnTgl.textContent = 'Show pricelist';
    }
    updateRefreshState();
  });

  
  // Инициализация
  updateRefreshState();
})();
</script>