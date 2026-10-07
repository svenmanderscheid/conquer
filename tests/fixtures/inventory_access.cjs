'use strict';
// Test-only access to the existing item-source destination API; no production hook.
async function captureInventory(page) {
  await page.addInitScript(() => {
    let factory;
    Object.defineProperty(window, 'ConquerPanels', {
      configurable:true,
      get:() => factory,
      set:original => { factory = function(...args) {
        const panels = original.apply(this, args);
        window.__inventoryTestPanels = panels;
        return panels;
      }; }
    });
  });
}
captureInventory.showItem = async (page, code) => {
  await page.waitForFunction(() => Boolean(window.__inventoryTestPanels));
  const found = await page.evaluate(code => window.__inventoryTestPanels.showInventoryItem(code), Number(code));
  if (!found) throw new Error('Item-source destination unavailable: ' + code);
};
module.exports = captureInventory;
