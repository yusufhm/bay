// @flow

import { makeExecutableSchema } from 'graphql-tools';

const typeDefs = `
  type SiteGroup {
    id: String!
    site_group_name: String
    git_url: String
    slack: Slack
    client: Client
  }

  type Site {
    id: String
    site_branch: String
  }

  type Client {
    client_name: String
    deploy_private_key: String
    created:String
    comment: String
    site_groups: [SiteGroup]
  }

  type Slack {
    webhook: String
    channel: String
    inform_start: Boolean
    inform_channel: String
  }

  type Query {
    allSiteGroups: [SiteGroup]
    allSites(environmentType: String!): [Site]
    siteByName(name: String!): Site
    allClients: [Client]
  }
`;

const resolvers = {
  Query: {
    allSiteGroups: (_, __, ctx) => {
      const { getState } = ctx;
      const { getAllSiteGroups } = ctx.selectors;

      return getAllSiteGroups(getState());
    },
    allSites: (_, args, ctx) => {
      const { getState } = ctx;
      const { getAllSites } = ctx.selectors;

      return getAllSites(getState(), args.environmentType);
    },
    siteByName: (_, args, ctx) => {
      const { getState } = ctx;
      const { getSiteByName } = ctx.selectors;

      return getSiteByName(getState(), args.name);
    },
    allClients: (_, __, ctx) => {
      const { getState } = ctx;
      const { getAllClients } = ctx.selectors;

      return getAllClients(getState());
    },
  },
  Client: {
    site_groups: (client, _, ctx) => {
      const { getState } = ctx;
      const { getSiteGroupsByClient } = ctx.selectors;

      return getSiteGroupsByClient(getState(), client);
    },
  },
  SiteGroup: {
    client: (siteGroup, _, ctx) => {
      const { getState } = ctx;
      const { getClientByName } = ctx.selectors;

      return getClientByName(getState(), siteGroup.client);
    },
  },
};

export default makeExecutableSchema({ typeDefs, resolvers });
