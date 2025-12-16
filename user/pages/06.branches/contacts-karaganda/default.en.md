---
title: Karaganda
hide_page_title: false
show_sidebar: true
hide_git_sync_repo_link: false
visible: true
process:
    markdown: true
    twig: true
---

he Karaganda branch of LLP “Center of Hematology” was opened in 2018 as part of a public-private partnership between the Akimat of Karaganda Region, the Department of Public Health of Karaganda Region, and the Center of Hematology.

---

**17 S. Seifullin Avenue, 2nd floor**

Tel.: **+7 747 095 5650** (weekdays from 08:00 to 17:00)

E-mail: karaganda@hemcenter.kz

<iframe src="https://yandex.com/map-widget/v1/?um=constructor%3Ae290cff7866c72ac63514d64fc910a9b640d860cd214d346c171ff654fcc2d0e&amp;source=constructor" width="100%" height="240" frameborder="0"></iframe>

---

The main objectives of this partnership are:
* the creation of a unique, highly specialized inpatient facility for providing medical services in the field of adult hematology;
* the integration of a system for dynamic outpatient monitoring of patients with blood disorders;
* early detection, dispensary observation, and prevention of hematological diseases;
* as well as the provision of medical care to patients of other profiles who have secondary hematological manifestations.

The branch employs qualified hematologists and physician assistants, and implements a unique information system that supports clinical decision-making in hematology and tracks all applied technologies, ensuring patient safety.

The staff of the Center provides a full range of medical, diagnostic, and therapeutic services, including genetic diagnostics and bone marrow immunohistochemistry, while maintaining partnerships with leading international clinics, such as the Russian Research Institute of Hematology and Transfusiology (St. Petersburg) and the National Medical Research Center of Hematology (Moscow).

Patients receiving medical care at the Center also have access to the services of the Regional Clinical Hospital, which allows the provision of comprehensive instrumental and laboratory diagnostics as well as consultations with specialists of other medical profiles.

At the outpatient department of the Center, both dispensary observation and primary patient consultations are conducted, and virtual consultations are available for patients and physicians of all specialties across the Karaganda Region.

[Drug Formulary](../../drug-formulary)

{% set city = 'karaganda' %}
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
  var city    = {{ city|json_encode|raw }};
  var lang    = {{ lang|json_encode|raw }};

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