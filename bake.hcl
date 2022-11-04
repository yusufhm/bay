variable "IMAGE_TAG" {
  default = "5.x"
}

variable "DOCKERHUB_NAMESPACE" {
  default = "singledigital"
}

variable "CONTEXT" {
  default = "images"
}

variable "LAGOON_IMAGE_VERSION" {
  # Used to control which version of upstream Lagoon
  # images the bay images are built from.
  default = "latest"
}

group "default" {
    targets = [
      "bay-ci-builder",
      "bay-php-cli",
      "bay-mariadb",
      "bay-nginx",
      "bay-node",
      "bay-php-fpm",
    ]
}

target "bay-ci-builder" {
  context       = "${CONTEXT}/bay-ci-builder"
  dockerfile    = "Dockerfile"

  platforms     = ["linux/amd64"]
  tags          = ["${DOCKERHUB_NAMESPACE}/bay-ci-builder:${IMAGE_TAG}"]

  args          = {
    LAGOON_IMAGE_VERSION = "${LAGOON_IMAGE_VERSION}"
  }
}

target "bay-php-cli" {
  context       = "${CONTEXT}/bay-php"
  dockerfile    = "Dockerfile.cli"

  platforms     = ["linux/amd64", "linux/arm64"]
  tags          = [
    // bay-cli is a legacy tag - should be removed eventually.
    "${DOCKERHUB_NAMESPACE}/bay-cli:${IMAGE_TAG}",
    "${DOCKERHUB_NAMESPACE}/bay-php-cli:${IMAGE_TAG}",
  ]

  args          = {
    LAGOON_IMAGE_VERSION = "${LAGOON_IMAGE_VERSION}"
  }
}

target "bay-mariadb" {
  context       = "${CONTEXT}/bay-mariadb"
  dockerfile    = "Dockerfile"

  platforms     = ["linux/amd64", "linux/arm64"]
  tags          = ["${DOCKERHUB_NAMESPACE}/bay-mariadb:${IMAGE_TAG}"]

  args          = {
    LAGOON_IMAGE_VERSION = "${LAGOON_IMAGE_VERSION}"
  }
}

target "bay-nginx" {
  context       = "${CONTEXT}/bay-nginx"
  dockerfile    = "Dockerfile"

  platforms     = ["linux/amd64", "linux/arm64"]
  tags          = ["${DOCKERHUB_NAMESPACE}/bay-nginx:${IMAGE_TAG}"]

  args          = {
    LAGOON_IMAGE_VERSION = "${LAGOON_IMAGE_VERSION}"
  }
}

target "bay-node" {
  context       = "${CONTEXT}/bay-node"
  dockerfile    = "Dockerfile"

  platforms     = ["linux/amd64", "linux/arm64"]
  tags          = [
    "${DOCKERHUB_NAMESPACE}/bay-node:${IMAGE_TAG}",
    "${DOCKERHUB_NAMESPACE}/ripple-node:${IMAGE_TAG}",
  ]

  args          = {
    LAGOON_IMAGE_VERSION = "${LAGOON_IMAGE_VERSION}"
  }
}

target "bay-php-fpm" {
  context       = "${CONTEXT}/bay-php"
  dockerfile    = "Dockerfile.fpm"

  platforms     = ["linux/amd64", "linux/arm64"]
  tags          = [
    // bay-php is a legacy tag - should be removed eventually.
    "${DOCKERHUB_NAMESPACE}/bay-php:${IMAGE_TAG}",
    "${DOCKERHUB_NAMESPACE}/bay-php-fpm:${IMAGE_TAG}",
  ]

  args          = {
    LAGOON_IMAGE_VERSION = "${LAGOON_IMAGE_VERSION}"
  }
}