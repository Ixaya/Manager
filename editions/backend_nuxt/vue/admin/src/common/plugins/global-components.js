// Register global components
export default {
  install: (app) => {
    // Tab Components
    app.component('tab-button', require('@/common/components/bootstrap/tab/TabButton').default)
    app.component('tab-pane', require('@/common/components/bootstrap/tab/TabPane').default)

    // Brand Components
    app.component('brand-logo', require('@/common/components/custom/logo/BrandLogo').default)
    app.component('brand-name', require('@/common/components/custom/logo/BrandName').default)

    // Icon Components
    app.component('icon-component', require('@/common/components/icons/IconComponent').default)
  }
}
