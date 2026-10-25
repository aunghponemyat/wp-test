"use strict";
const input = document.getElementById("ccs_short_description");
const counter = document.getElementById("ccs-counter");
if (input && counter)
  input.addEventListener("input", () => {
    counter.textContent = `${Array.from(input.value).length} / 200`;
  });
