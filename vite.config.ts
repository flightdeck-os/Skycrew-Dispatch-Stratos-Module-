import { createPluginConfig } from "@skyvexsoftware/stratos-sdk/vite";
import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";

export default createPluginConfig({
  ui: { entry: "src/ui/index.tsx" },
  vite: {
    plugins: [tailwindcss(), react()],
  },
});
