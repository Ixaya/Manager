const { defineConfig } = require('@vue/cli-service')
const webpack = require('webpack')
const TerserPlugin = require('terser-webpack-plugin')
const dotenv = require('dotenv')
const path = require('path')

// Cargar las variables de entorno manualmente
const envFile = process.env.NODE_ENV === 'production' ? '.env.prod' : '.env.dev'
dotenv.config({ path: path.resolve(__dirname, envFile) })

console.log('Entorno de ejecución:', process.env.VUE_APP_ENV)

module.exports = defineConfig({
  publicPath: process.env.VUE_APP_ENV === 'production' ? process.env.BASE_URL : '/',

  pluginOptions: {
    i18n: {
      locale: process.env.VUE_APP_I18N_LOCALE || 'en',
      fallbackLocale: 'en',
      localeDir: process.env.VUE_APP_I18N_FALLBACK_LOCALE || 'en',
      enableInSFC: false
    }
  },

  transpileDependencies: true,

  configureWebpack: (config) => {
    // Configuraciones específicas para diferentes entornos
    if (process.env.VUE_APP_ENV === 'production') {
      // Configuraciones para producción
      config.optimization = {
        splitChunks: {
          chunks: 'all'
        },
        runtimeChunk: 'single'
      }
      config.plugins = [
        ...config.plugins,
        new TerserPlugin({
          terserOptions: {
            compress: {
              drop_console: true // Elimina los console.log en producción
            }
          }
        }),
        new webpack.DefinePlugin({
          __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: JSON.stringify(false)
        })
      ]
    } else {
      // Configuraciones para desarrollo
      config.devtool = 'source-map' // Mapa de origen para depuración
      config.plugins = [
        ...config.plugins,
        new webpack.DefinePlugin({
          __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: JSON.stringify(true)
        })
      ]
    }
  }
})
