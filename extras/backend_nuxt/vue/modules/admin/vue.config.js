const { defineConfig } = require('@vue/cli-service')
const webpack = require('webpack')
const TerserPlugin = require('terser-webpack-plugin')
const dotenv = require('dotenv')
const path = require('path')

// Función que carga las variables de entorno según el archivo apropiado
function loadEnv(env) {
  const envFile = getEnvFile(env)

  // Verificar si el archivo .env existe, si no, lanzar un error
  const result = dotenv.config({ path: path.resolve(__dirname, envFile) })

  if (result.error) {
    console.error(`Error al cargar el archivo de entorno ${envFile}:`, result.error)
    throw new Error(`No se pudo cargar el archivo de entorno ${envFile}`)
  }
}

// Función que obtiene el archivo de variables de entorno adecuado
function getEnvFile(env) {
  console.log(`Entorno de ejecución: ${env}`)
  switch (env) {
    case 'prod':
      return '.env.prod'
    case 'uat':
      return '.env.uat'
    case 'dev':
      return '.env.dev'
    case 'local':
      return '.env.local'
    default:
      throw new Error('Entorno no especificado o no válido. Se requiere un entorno válido como "prod", "uat", "dev" o "local".')
  }
}

// Comprobar si VUE_APP_ENV está definido, si no, establecer un valor predeterminado de 'dev'
const env = process.env.NODE_ENV || 'dev'

try {
  // Cargar las variables de entorno
  loadEnv(env)
} catch (error) {
  console.error(error.message)
  process.exit(1) // Detener el proceso si no se puede cargar el entorno correctamente
}

module.exports = defineConfig({
  publicPath: getPublicPath(env),
  outputDir: getOutputPath(env), // Configura el directorio de salida

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
    switch (env) {
      case 'prod':
        configureProduction(config)
        break
      case 'dev':
        configureDevelopment(config)
        break
      case 'uat':
        configureUat(config)
        break
      case 'local':
        configureLocal(config)
        break
      default:
        console.error('No se ha especificado un entorno de ejecución.')
        process.exit(1)
    }
  }
})

// Funciones de configuración según el entorno
function configureProduction(config) {
  config.optimization = {
    splitChunks: {
      chunks: 'all',
      minSize: 30000,
      maxSize: 250000,
      maxAsyncRequests: 30,
      maxInitialRequests: 30,
      automaticNameDelimiter: '~',
      cacheGroups: {
        defaultVendors: {
          test: /[\\/]node_modules[\\/]/,
          priority: -10,
          reuseExistingChunk: true
        },
        default: {
          minChunks: 2,
          priority: -20,
          reuseExistingChunk: true
        }
      }
    },
    runtimeChunk: 'single',
    minimize: true,
    moduleIds: 'deterministic',
    minimizer: [
      new TerserPlugin({
        terserOptions: {
          output: {
            comments: false,
            ascii_only: true
          },
          compress: {
            drop_console: true
          },
          ecma: 2015
        },
        extractComments: false
      })
    ]
  }

  // Configura los nombres de archivo para evitar problemas de caché
  config.output.filename = '[name].[contenthash].js'
  config.output.chunkFilename = '[name].[contenthash].js'

  config.plugins = [
    ...config.plugins,
    new webpack.DefinePlugin({
      __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: JSON.stringify(false)
    })
  ]
  config.devtool = false
}

function configureDevelopment(config) {
  config.optimization = {
    splitChunks: {
      chunks: 'all'
    },
    runtimeChunk: 'single'
  }
  config.plugins.push(
    new webpack.DefinePlugin({
      __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: JSON.stringify(true)
    })
  )
  config.devtool = 'source-map' // Mapa de origen para depuración
}

function configureUat(config) {
  config.optimization = {
    splitChunks: {
      chunks: 'all'
    },
    runtimeChunk: 'single',
    minimize: true,
    minimizer: [
      new TerserPlugin({
        terserOptions: {
          output: {
            comments: false,
            ascii_only: true
          },
          compress: {
            drop_console: true
          },
          ecma: 2015
        }
      })
    ]
  }
  config.plugins.push(
    new webpack.DefinePlugin({
      __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: JSON.stringify(false)
    })
  )
  config.devtool = 'hidden-source-map'
}

function configureLocal(config) {
  config.plugins.push(
    new webpack.DefinePlugin({
      __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: JSON.stringify(true)
    })
  )
  config.devtool = 'eval-source-map'
}

function getPublicPath() {
  return process.env.BASE_URL || '/'
}

function getOutputPath() {
  return process.env.BASE_URL ? `../../../public/${process.env.BASE_URL}` : 'dist'
}
