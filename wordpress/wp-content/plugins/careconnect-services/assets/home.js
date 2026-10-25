"use strict";
const list = document.getElementById("ccs_featured");
for (const direction of ["up", "down"]) {
  document.getElementById(`ccs-${direction}`)?.addEventListener("click", () => {
    const options = [...list.options];
    if (direction === "down") options.reverse();
    for (const option of options) {
      const neighbor =
        direction === "up"
          ? option.previousElementSibling
          : option.nextElementSibling;
      if (option.selected && neighbor && !neighbor.selected) {
        if (direction === "up") list.insertBefore(option, neighbor);
        else list.insertBefore(neighbor, option);
      }
    }
    list.focus();
  });
}
