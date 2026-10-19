import { defineConfig, devices } from '@playwright/test';
export default defineConfig({
  testDir:'./e2e', timeout:45000, fullyParallel:false, workers:1,
  reporter:[['list'],['html',{open:'never'}]],
  use:{channel:'chrome',baseURL:'http://127.0.0.1:4151',trace:'retain-on-failure',screenshot:'only-on-failure'},
  projects:[{name:'chromium',use:{...devices['Desktop Chrome']}}],
  webServer:[
    {command:'node e2e-api.mjs',url:'http://127.0.0.1:4157/up',reuseExistingServer:false,timeout:60000},
    {command:'npm --prefix ../ms/orbita-admin run dev',url:'http://127.0.0.1:4152/admin/health',env:{APP_ENV:'local',ORIGIN:'http://127.0.0.1:4151',ORBITA_API_ORIGIN_INTERNAL:'http://127.0.0.1:4157'},reuseExistingServer:false,timeout:60000},
    {command:'npm --prefix ../ms/orbita-front run dev',url:'http://127.0.0.1:4151/health',env:{APP_ENV:'local',ORBITA_DEMO:'false',ORBITA_PUBLIC_URL:'http://127.0.0.1:4151',ORBITA_API_URL_INTERNAL:'http://127.0.0.1:4157/graphql',ORBITA_API_PROXY:'http://127.0.0.1:4157'},reuseExistingServer:false,timeout:60000}
  ]
});
